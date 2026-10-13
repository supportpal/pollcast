<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garbage collection filters channels and members by `updated_at`. Without an index the channel
 * delete scans the whole table and, under InnoDB's default isolation level, exclusive-locks every
 * row it reads rather than only the stale ones.
 */
class AddUpdatedAtIndexesToPollcastTables extends Migration
{
    /** @var string[] */
    private $tables = ['pollcast_channel', 'pollcast_channel_members'];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->index('updated_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['updated_at']);
            });
        }
    }
}
