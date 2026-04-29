<?php

namespace Safarovitch\TelegramBot\Models;

use DefStudio\Telegraph\Models\TelegraphChat as BaseTelegraphChat;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegraphChat extends BaseTelegraphChat
{
    protected $fillable = [
        'chat_id',
        'name',
        'user_id',
        'current_state',
        'state_data',
        'language',
    ];

    protected $casts = [
        'state_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function updateState(string $state, array $data = []): void
    {
        $this->update([
            'current_state' => $state,
            'state_data' => $data,
        ]);
    }

    public function clearState(): void
    {
        $this->update([
            'current_state' => null,
            'state_data' => null,
        ]);
    }
}
