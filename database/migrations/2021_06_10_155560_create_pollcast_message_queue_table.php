<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePollcastMessageQueueTable extends Migration
{
    /** @var string */
    private $table = 'pollcast_message_queue';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create($this->table, function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->uuid('id')->primary();

            $table->uuid('channel_id');
            $table->foreign('channel_id')->references('id')->on('pollcast_channel')->onDeleteCascade();

            $table->uuid('member_id')->nullable();
            $table->foreign('member_id')->references('id')->on('pollcast_channel_members')->onDeleteCascade();

            $table->text('event');
            $table->mediumText('payload');
            $table->timestamps(6);

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop($this->table);
    }
}
