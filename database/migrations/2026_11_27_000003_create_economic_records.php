<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_events', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $t->string('description');
            $t->string('nature', 16);
            $t->decimal('amount', 18, 2);
            $t->char('currency', 3)->default('BRL');
            $t->date('competence_date');
            $t->uuid('category_public_id');
            $t->uuid('created_by');
            $t->string('origin', 16)->default('manual');
            $t->timestampTz('locked_at', 6)->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz(6);
            $t->unique(['organization_id', 'public_id'], 'economic_events_org_uuid_unique');
            $t->foreign(['organization_id', 'category_public_id', 'nature'], 'economic_events_category_fk')->references(['organization_id', 'public_id', 'nature'])->on('financial_categories')->restrictOnDelete()->restrictOnUpdate();
            $t->index(['organization_id', 'competence_date', 'id'], 'economic_events_competence_index');
            $t->index(['organization_id', 'nature', 'category_public_id', 'id'], 'economic_events_classification_index');
        });
        Schema::create('financial_commitments', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $t->uuid('event_public_id');
            $t->string('nature', 16);
            $t->decimal('expected_amount', 18, 2);
            $t->char('currency', 3)->default('BRL');
            $t->date('due_date');
            $t->string('status', 24)->default('pending');
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz(6);
            $t->unique(['organization_id', 'public_id'], 'financial_commitments_org_uuid_unique');
            $t->foreign(['organization_id', 'event_public_id'], 'financial_commitments_event_fk')->references(['organization_id', 'public_id'])->on('economic_events')->restrictOnDelete()->restrictOnUpdate();
            $t->index(['organization_id', 'due_date', 'id'], 'financial_commitments_due_index');
            $t->index(['organization_id', 'event_public_id', 'id'], 'financial_commitments_event_index');
        });
        DB::statement("ALTER TABLE economic_events ADD CONSTRAINT economic_events_values_valid CHECK(length(btrim(description)) BETWEEN 1 AND 255 AND description !~ '[[:cntrl:]]' AND nature IN ('income','cost','expense') AND amount > 0 AND currency='BRL' AND origin='manual' AND version > 0 AND updated_at >= created_at AND competence_date BETWEEN DATE '1000-01-01' AND DATE '9999-12-31')");
        DB::statement("ALTER TABLE financial_commitments ADD CONSTRAINT financial_commitments_values_valid CHECK(nature IN ('receivable','payable') AND expected_amount > 0 AND currency='BRL' AND status='pending' AND version > 0 AND updated_at >= created_at AND due_date BETWEEN DATE '1000-01-01' AND DATE '9999-12-31')");
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION check_financial_aggregate() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE org bigint; event_uuid uuid; economic numeric; expected numeric; wrong bigint; event_nature text;
BEGIN
    IF TG_OP = 'UPDATE' AND (NEW.organization_id <> OLD.organization_id OR NEW.public_id <> OLD.public_id) THEN
        RAISE EXCEPTION 'Financial ownership and identity are immutable' USING ERRCODE='23514';
    END IF;
    IF TG_TABLE_NAME = 'economic_events' THEN
        org := NEW.organization_id; event_uuid := NEW.public_id;
    ELSE
        IF TG_OP = 'UPDATE' AND NEW.event_public_id <> OLD.event_public_id THEN
            RAISE EXCEPTION 'Commitment origin is immutable' USING ERRCODE='23514';
        END IF;
        IF TG_OP = 'DELETE' THEN org := OLD.organization_id; event_uuid := OLD.event_public_id;
        ELSE org := NEW.organization_id; event_uuid := NEW.event_public_id;
        END IF;
    END IF;
    SELECT amount,nature INTO economic,event_nature FROM economic_events WHERE organization_id=org AND public_id=event_uuid;
    IF FOUND THEN
        SELECT SUM(expected_amount),COUNT(*) FILTER(WHERE nature <> CASE WHEN event_nature='income' THEN 'receivable' ELSE 'payable' END OR currency <> 'BRL')
          INTO expected,wrong FROM financial_commitments WHERE organization_id=org AND event_public_id=event_uuid;
        IF expected IS NULL OR expected <> economic OR wrong > 0 THEN
            RAISE EXCEPTION 'Economic event requires consistent commitments' USING ERRCODE='23514';
        END IF;
    END IF;
    RETURN NULL;
END;
$$;
CREATE CONSTRAINT TRIGGER economic_events_aggregate_check AFTER INSERT OR UPDATE ON economic_events DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_financial_aggregate();
CREATE CONSTRAINT TRIGGER financial_commitments_aggregate_check AFTER INSERT OR UPDATE OR DELETE ON financial_commitments DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_financial_aggregate();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_commitments');
        Schema::dropIfExists('economic_events');
        DB::statement('DROP FUNCTION IF EXISTS check_financial_aggregate()');
    }
};
