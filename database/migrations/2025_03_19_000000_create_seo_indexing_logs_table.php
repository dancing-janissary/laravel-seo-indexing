<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_indexing_logs', function (Blueprint $table) {
            $table->id();

            /*
            | What was submitted
            */
            $table->string('url');
            $table->string('action', 20);           // URL_UPDATED | URL_DELETED

            /*
            | Which engine handled it
            | e.g. 'google', 'indexnow:www.bing.com', 'indexnow:yandex.com'
            */
            $table->string('engine', 60);

            /*
            | Result
            */
            $table->boolean('success')->default(false)->index();
            $table->unsignedSmallInteger('http_status')->default(0);
            $table->text('message')->nullable();     // error message if failed
            $table->json('payload')->nullable();     // raw API response

            /*
            | Traceability — which model triggered the submission
            */
            $table->nullableMorphs('indexable');    // indexable_type + indexable_id

            /*
            | Job tracking
            */
            $table->uuid('job_id')->nullable()->index(); // links log to queue job
            $table->boolean('queued')->default(false);

            $table->timestamps();

            /*
            | Indexes for common queries:
            | - "all failed submissions"
            | - "all submissions for a given URL"
            | - "submissions for a given model"
            */
            $table->index(['url', 'engine']);
            $table->index(['success', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_indexing_logs');
    }
};