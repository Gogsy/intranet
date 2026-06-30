<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $fillable = [
        'mailer', 'host', 'port', 'username', 'password',
        'encryption', 'from_address', 'from_name',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'port'     => 'integer',
    ];

    /** The single settings row (creates an empty one if missing). */
    public static function current(): self
    {
        return static::firstOrNew([]);
    }

    /**
     * Push these settings into Laravel's mail config at runtime so all mail
     * (invites, password resets, test mail) uses them instead of .env.
     */
    public function apply(): void
    {
        if (empty($this->host)) {
            return;
        }

        config([
            'mail.default' => $this->mailer ?: 'smtp',
            'mail.mailers.smtp.host' => $this->host,
            'mail.mailers.smtp.port' => $this->port ?: 587,
            'mail.mailers.smtp.username' => $this->username,
            'mail.mailers.smtp.password' => $this->password,
            'mail.mailers.smtp.encryption' => $this->encryption ?: null,
            'mail.from.address' => $this->from_address ?: config('mail.from.address'),
            'mail.from.name' => $this->from_name ?: config('mail.from.name'),
        ]);
    }
}
