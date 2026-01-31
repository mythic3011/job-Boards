<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
| Authentication routes (Register, Login, Logout) are automatically
| registered by Laravel Fortify.
|
*/

/*
|--------------------------------------------------------------------------
| Installation Routes (Must be first)
|--------------------------------------------------------------------------
*/
require __DIR__.'/install.php';

/*
|--------------------------------------------------------------------------
| Authentication Routes (Override Fortify defaults)
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Home Route
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    if (!Schema::hasTable('settings') || !Setting::isSetupCompleted()) {
        return redirect()->route('install.index');
    }
    return view('welcome');
})->name('home');

// // Test route for dropdown navigation
// Route::get('/test-nav', function () {
//     if (!auth()->check()) {
//         return 'Please log in first: <a href="/login">Login</a>';
//     }
    
//     return '
//     <html>
//     <head>
//         <title>Test Navigation</title>
//         <script src="https://cdn.tailwindcss.com"></script>
//     </head>
//     <body class="bg-gray-50 p-8">
//         <h1 class="text-2xl font-bold mb-4">Navigation Test</h1>
//         <p class="mb-4">User: ' . auth()->user()->nickname . '</p>
        
//         <div class="relative inline-block">
//             <button 
//                 onclick="toggleProfileDropdown(event)"
//                 class="flex items-center text-sm text-gray-700 hover:text-gray-900 transition-colors bg-white px-4 py-2 rounded border"
//                 id="profile-button"
//                 aria-expanded="false"
//             >
//                 <span>' . auth()->user()->nickname . '</span>
//                 <svg class="ml-1 h-4 w-4 transition-transform duration-200" id="profile-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
//                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
//                 </svg>
//             </button>
            
//             <div 
//                 id="profile-menu"
//                 class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 border border-gray-200 opacity-0 scale-95 transform transition-all duration-100 pointer-events-none"
//                 style="display: none;"
//             >
//                 <a href="/profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">View Profile</a>
//                 <a href="/profile/edit" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit Profile</a>
//                 <a href="/profile/password" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Change Password</a>
//                 <div class="border-t border-gray-100 my-1"></div>
//                 <form method="POST" action="/logout" class="block">
//                     <input type="hidden" name="_token" value="' . csrf_token() . '">
//                     <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign Out</button>
//                 </form>
//             </div>
//         </div>

//         <script>
//         let profileDropdownOpen = false;

//         function toggleProfileDropdown(event) {
//             event.preventDefault();
//             event.stopPropagation();
            
//             const button = document.getElementById("profile-button");
//             const menu = document.getElementById("profile-menu");
//             const arrow = document.getElementById("profile-arrow");
            
//             if (!button || !menu || !arrow) return;
            
//             if (profileDropdownOpen) {
//                 closeProfileDropdown();
//             } else {
//                 openProfileDropdown();
//             }
//         }
        
//         function openProfileDropdown() {
//             const button = document.getElementById("profile-button");
//             const menu = document.getElementById("profile-menu");
//             const arrow = document.getElementById("profile-arrow");
            
//             profileDropdownOpen = true;
//             button.setAttribute("aria-expanded", "true");
//             menu.style.display = "block";
            
//             setTimeout(() => {
//                 menu.classList.remove("opacity-0", "scale-95", "pointer-events-none");
//                 menu.classList.add("opacity-100", "scale-100");
//             }, 10);
            
//             arrow.style.transform = "rotate(180deg)";
//         }
        
//         function closeProfileDropdown() {
//             const button = document.getElementById("profile-button");
//             const menu = document.getElementById("profile-menu");
//             const arrow = document.getElementById("profile-arrow");
            
//             profileDropdownOpen = false;
//             button.setAttribute("aria-expanded", "false");
//             menu.classList.remove("opacity-100", "scale-100");
//             menu.classList.add("opacity-0", "scale-95", "pointer-events-none");
//             arrow.style.transform = "rotate(0deg)";
            
//             setTimeout(() => {
//                 if (!profileDropdownOpen) {
//                     menu.style.display = "none";
//                 }
//             }, 100);
//         }
        
//         document.addEventListener("click", function(event) {
//             if (profileDropdownOpen) {
//                 const dropdown = event.target.closest(".relative");
//                 const button = document.getElementById("profile-button");
                
//                 if (!dropdown || !dropdown.contains(button)) {
//                     closeProfileDropdown();
//                 }
//             }
//         });
        
//         document.addEventListener("keydown", function(event) {
//             if (event.key === "Escape" && profileDropdownOpen) {
//                 closeProfileDropdown();
//                 document.getElementById("profile-button").focus();
//             }
//         });
//         </script>
//     </body>
//     </html>';
// })->name('test.nav');

// // Test route for profile validation
// Route::get('/test-profile-validation', function () {
//     if (!auth()->check()) {
//         return 'Please log in first: <a href="/login">Login</a>';
//     }
    
//     $user = auth()->user();
//     $profileService = app(\App\Services\ProfileService::class);
    
//     // Test data
//     $testData = [
//         'nickname' => 'Test User',
//         'email' => $user->email, // Use current email
//     ];
    
//     try {
//         $result = $profileService->updateProfile($user, $testData, request());
//         return 'Profile update successful! New nickname: ' . $result->nickname;
//     } catch (\Illuminate\Validation\ValidationException $e) {
//         return 'Validation errors: ' . json_encode($e->errors(), JSON_PRETTY_PRINT);
//     } catch (\Exception $e) {
//         return 'Error: ' . $e->getMessage() . '<br>Trace: ' . $e->getTraceAsString();
//     }
// })->name('test.profile.validation');

/*
|--------------------------------------------------------------------------
| Feature-Specific Route Groups
|--------------------------------------------------------------------------
*/
require __DIR__.'/images.php';
require __DIR__.'/jobs.php';
require __DIR__.'/profile.php';
require __DIR__.'/admin.php';
