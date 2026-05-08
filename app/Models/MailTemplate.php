<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'subject',
        'body_html',
        'content_html',
        'body_text',
        'variables_json',
        'is_active',
        'locale',
        'use_visual_editor',
        'reset_at',
    ];

    protected $casts = [
        'variables_json'     => 'array',
        'is_active'          => 'boolean',
        'use_visual_editor'  => 'boolean',
        'reset_at'           => 'datetime',
    ];


    /**
     * Find an active template by its key.
     */
    public static function findActive(string $key): ?self
    {
        return static::where('key', $key)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Render subject with variable substitution.
     */
    public function renderSubject(array $vars): string
    {
        return self::interpolate($this->subject, $vars);
    }

    /**
     * Render HTML body with variable substitution.
     */
    public function renderHtml(array $vars): string
    {
        return self::interpolate($this->body_html, $vars);
    }

    /**
     * Render plain-text body with variable substitution.
     */
    public function renderText(array $vars): ?string
    {
        if (!$this->body_text) {
            return null;
        }
        return self::interpolate($this->body_text, $vars);
    }

    /**
     * Replace {{variable}} placeholders in a string.
     */
    public static function interpolate(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace(
                ['{{' . $key . '}}', '{{ ' . $key . ' }}'],
                (string) ($value ?? ''),
                $template
            );
        }
        // Remove any remaining unresolved placeholders
        return preg_replace('/\{\{[^}]+\}\}/', '', $template);
    }
}
