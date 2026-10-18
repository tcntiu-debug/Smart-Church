<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `tiu_member` predates Laravel's auth tables, so it never got the
 * `remember_token` column every `Authenticatable` model is expected to have
 * (2026_04_28_000005 creates the table without it).
 *
 * Nothing reads the column, which is why the gap survived this long - but Laravel
 * writes it on both auth paths:
 *
 *   - `ResetsPasswords::reset()` calls `$user->setRememberToken(Str::random(60))`
 *     right before `save()`, and
 *   - `Auth::attempt($credentials, true)` (the login form's "remember me") calls
 *     `updateRememberToken()` on the same row.
 *
 * The UPDATE then carries a column MySQL cannot find:
 *
 *   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'remember_token' in 'field list'
 *
 * That aborts the whole statement, so the new password was never stored and the
 * reset form answered "Server Error" - and because the single-use token had
 * already been deleted, the member could not even retry with the same link.
 *
 * Nullable VARCHAR(100) - exactly what `$table->rememberToken()` puts in the stock
 * `users` table. Guarded in both directions, because the SFTP-only hosts apply
 * this file through `app:sync-schema` and a database that was fixed by hand (or
 * by phpMyAdmin) must not fail on a re-run.
 */
class AddRememberTokenToTiuMemberTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('tiu_member') || Schema::hasColumn('tiu_member', 'remember_token')) {
            return;
        }

        Schema::table('tiu_member', function (Blueprint $table) {
            // after('password') keeps the auth columns together when somebody
            // opens the table in phpMyAdmin.
            $table->rememberToken()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable('tiu_member') || ! Schema::hasColumn('tiu_member', 'remember_token')) {
            return;
        }

        Schema::table('tiu_member', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
}
