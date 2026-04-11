<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', fn ($user, $id): bool => (int) $user->id === (int) $id);

Broadcast::channel('tyanc.conversations.{conversation}', fn (User $user, Conversation $conversation): bool => $conversation->participants()->whereKey($user->getKey())->exists());
Broadcast::channel('tyanc.users.{id}.messages', fn (User $user, string $id): bool => (string) $user->id === $id);
