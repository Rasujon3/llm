<?php

use App\Modules\Chats\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

//Route::prefix('v1/chats')->middleware('auth:sanctum')->group(function () {
Route::prefix('v1/chats')->group(function () {

    Route::get('/conversations', [ChatController::class, 'conversations']);
    Route::get('/chat-histories', [ChatController::class, 'index']);
    Route::post('/chat', [ChatController::class, 'handleChat']);

//    Route::get('/list', [ChatController::class, 'index'])->name('chats.list'); // List data
    // ✅ Routes accessible by both owner & receptionist
    Route::middleware('roles:owner,receptionist,user')->group(function () {
//        Route::get('/list', [ChatController::class, 'index'])->name('chats.list'); // List data
    });

    // ✅ User-only routes
    Route::middleware('roles:user')->group(function () {
//        Route::post('/create', [ChatController::class, 'store'])->name('chats.store'); // Create data
//        Route::get('/view/{facility}', [ChatController::class, 'show'])->name('chats.view'); // View data
//        Route::post('/update/{facility}', [ChatController::class, 'update'])->name('chats.update'); // Update data
//        Route::delete('/delete/{facility}', [ChatController::class, 'destroy'])->name('chats.delete'); // Delete data
    });
});
