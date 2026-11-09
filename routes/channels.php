<?php

use App\Broadcasting\IdentityActivityChannel;
use App\Broadcasting\UserChannel;
use Illuminate\Support\Facades\Broadcast;

// RT1 — hanya kanal privat; tidak ada kanal presence (W168).
Broadcast::channel('App.Models.User.{id}', UserChannel::class);
Broadcast::channel('identity.activity', IdentityActivityChannel::class);
