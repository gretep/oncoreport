<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create(
            'jobs',
            static function (Blueprint $table) {
                $table->id();
                $table->string('sample_code')->nullable()->index();
                $table->string('name')->nullable()->default(null);
                $table->string('job_type');
                $table->enum('status', ['ready', 'queued', 'processing', 'completed', 'failed'])->default('ready');
                $table->json('job_parameters');
                $table->json('job_output');
                $table->longText('log');
                $table->unsignedBigInteger('user_id')->index();
                $table->foreign('user_id', 'user_id_to_user_foreign_key')->references('id')->on('users')
                      ->onDelete('cascade')->onUpdate('cascade');
                $table->timestamps();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
}
