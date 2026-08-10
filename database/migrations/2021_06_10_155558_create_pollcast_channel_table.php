<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePollcastChannelTable extends Migration
{
    /** @var string */
    private $table = 'pollcast_channel';

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

            $name = $table->text('name');
            if (($collation = $this->nameCollation()) !== null) {
                $name->collation($collation);
            }

            $table->timestamps();

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

    /**
     * Channel names must compare byte for byte, case-sensitive.
     */
    private function nameCollation(): ?string
    {
        $driver = Schema::getConnection()->getDriverName();

        return in_array($driver, ['mysql', 'mariadb'], true) ? 'utf8mb4_bin' : null;
    }
}
