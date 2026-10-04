// >>> nebula-theme (managed by the Nebula installer - do not edit between the markers)
Route::group(['prefix' => 'nebula'], function () {
    Route::get('/', [\Pterodactyl\Http\Controllers\Admin\NebulaThemeController::class, 'index'])->name('admin.nebula');
    Route::post('/', [\Pterodactyl\Http\Controllers\Admin\NebulaThemeController::class, 'update']);
});
// <<< nebula-theme
