<?php

use App\Http\Controllers\BotManController;
use App\Http\Middleware\CheckUpForPermissionOrSkip;
use App\Http\Middleware\ConversationMiddleware;
use App\System\ApplicationPermissions;
use BotMan\BotMan\BotMan;
use BotMan\Drivers\VK\VkCommunityCallbackDriver;

/** @var BotMan $botman */
$botman = resolve('botman');

/**
 * Listeners
 */

// VK driver-specific confirmation event handler
$botman->group(['driver' => [VkCommunityCallbackDriver::class]], function(BotMan $bot) {
    $bot->on("confirmation", BotManController::class . "@VKConfirmationToken");
});

$middleware = new ConversationMiddleware();
$botman->middleware->received($middleware);
$botman->middleware->matching($middleware);

/***********************
 * Command listeners
 ***********************
 * Note: %%%{{{ and }}}%%% brackets are used to get the language-native pattern (see ConversationMiddleware->matching method).
 */

// Menu
// TODO: improve patterns for cancelling the conversations (see translation files)
$botman->hears(
    [
        __('pattern.cancel-conversation', [],'en'),
        '%%%{{{pattern.cancel-conversation}}}%%%'
    ],
    BotManController::class . "@sendMenu"
)->stopsConversation();

$botman->hears(
    [
        __('pattern.menu-start', [],'en'),
        "%%%{{{pattern.menu-start}}}%%%"
    ],
    BotManController::class . "@sendMenu"
);


// Generating the meme
$botman->hears(
    "%%%{{{pattern.generate-meme-conversation}}}%%%",
    BotManController::class . "@startGenerateMemeConversation"
);

// Manually created memes by users
$botman->hears(
    "%%%{{{pattern.create-meme-conversation}}}%%%",
    BotManController::class . "@startCreateMemeConversation"
);

// "About" conversation
$botman->hears(
    "%%%{{{pattern.about-conversation}}}%%%",
    BotManController::class . "@startAboutConversation"
);

// Manage banks conversation
$botman->hears(
    "%%%{{{pattern.manage-banks}}}%%%",
    BotManController::class . "@startManageBanksConversation"
);

// Language selection
$botman->hears(
    [
        __('pattern.language-selection', [],'en'), // Also include default patterns in English
        "%%%{{{pattern.language-selection}}}%%%",
    ],
    BotManController::class . "@startSelectLanguageConversation"
);

// Admin menu
$botman->group(['middleware' => new CheckUpForPermissionOrSkip(ApplicationPermissions::SHOW_ADMIN_MENU)], function(BotMan $bot) {
    $bot->hears(
        "%%%{{{pattern.admin-menu}}}%%%",
        BotManController::class . "@showAdminMenu"
    );
});

$botman->fallback('App\Http\Controllers\FallbackController@index');