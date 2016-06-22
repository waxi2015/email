<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmailTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->string('identifier')->nullable();
            $table->string('receiver')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->string('layout')->nullable();
            $table->timestamps();
        });

        DB::table('email_templates')->insert(
            array(
                'identifier' => 'add-administrator',
                'receiver' => 'Administrator',
                'name' => 'New administrator',
                'description' => 'Administrator added a new administrator',
                'subject' => 'You are added as Administrator',
                'content' => '<p>Dear Team Member,</p>\r\n\r\n<p> </p>\r\n\r\n<p>An administrator account has been created on your behalf. Please head over to our <a href="{{url}}" target="_blank">administration website</a> and use your email address with the initial password <strong>{{password}}</strong> to log in.</p>\r\n',
                'layout' => '',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            )
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('email_templates');
    }
}