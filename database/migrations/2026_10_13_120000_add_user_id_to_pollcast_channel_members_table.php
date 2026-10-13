<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var string */
    private $table = 'pollcast_channel_members';

    public function up(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            $table->string('user_id', 191)->nullable()->after('socket_id');
            $table->index(['channel_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            $table->dropIndex(['channel_id', 'user_id']);
            $table->dropColumn('user_id');
        });
    }
};
