<div wire:poll.30s="checkMaintenanceStatus">
    @if($maintenanceActive && !$isAdmin)
        <!-- Maintenance Modal Overlay -->
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md bg-white rounded-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-300">
                <!-- Header -->
                <div class="px-6 py-5 bg-gradient-to-r from-red-50 to-orange-50 border-b border-red-200">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">System Maintenance</h2>
                            <p class="text-sm text-gray-600">Service temporarily unavailable</p>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="px-6 py-6">
                    <!-- Alert Message -->
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4">
                        <p class="text-sm text-amber-900">
                            {{ $maintenanceMessage }}
                        </p>
                    </div>

                    <!-- Info Text -->
                    <div class="space-y-4 mb-6">
                        <div class="flex gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <h3 class="font-medium text-gray-900 text-sm">What's happening?</h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    Our administrators are making important updates to improve your experience. We appreciate your patience.
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <h3 class="font-medium text-gray-900 text-sm">How long?</h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    Maintenance typically takes less than an hour. Please check back soon.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Loading Animation -->
                    <div class="flex justify-center mb-6">
                        <div class="flex gap-1">
                            <div class="w-2 h-2 bg-red-600 rounded-full animate-bounce" style="animation-delay: 0s;"></div>
                            <div class="w-2 h-2 bg-red-600 rounded-full animate-bounce" style="animation-delay: 0.2s;"></div>
                            <div class="w-2 h-2 bg-red-600 rounded-full animate-bounce" style="animation-delay: 0.4s;"></div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t flex gap-3">
                    <button
                        type="button"
                        wire:click="goHome"
                        class="flex-1 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition text-sm"
                    >
                        Back to Home
                    </button>
                    <button
                        type="button"
                        wire:click="logout"
                        class="flex-1 px-4 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-900 font-medium rounded-lg transition text-sm"
                    >
                        Logout
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
