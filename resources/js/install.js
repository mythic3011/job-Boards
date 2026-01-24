import QRCode from 'qrcode';
import { TOTP, generateSecret, generateURI, verify } from 'otplib';

(function() {
    'use strict';

    const CONFIG = {
        INSTALL_PATH: '/install',
        DELAYS: {
            INIT: 50,
            RENDER: 10,
            FALLBACK: 100
        },
        PASSWORD_MIN_LENGTH: 12,
        NAME_MIN_LENGTH: 2,
        STEP_LABELS: {
            2: 'Admin',
            3: 'Complete'
        }
    };

    const SELECTORS = {
        CSRF_TOKEN: 'meta[name="csrf-token"]',
        CONTENT: '#content',
        USERNAME: '#username',
        NAME: '#name',
        EMAIL: '#email',
        PASSWORD: '#password',
        CONFIRM: '#confirm',
        DEMO: '#demo',
        APP_NAME: '#app_name',
        APP_URL: '#app_url',
        TIMEZONE: '#timezone'
    };

    const ENDPOINTS = {
        COMPLETE: '/install/complete'
    };

    const utils = {
        generateId: () => {
            if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
                return crypto.randomUUID();
            }
            return `install_${Date.now()}_${Math.random().toString(36).slice(2, 11)}`;
        },

        getCSRFToken: () => {
            return $(SELECTORS.CSRF_TOKEN).attr('content') || '';
        },

        isInstallPage: () => {
            return window.location.pathname === CONFIG.INSTALL_PATH;
        },

        isDOMReady: () => {
            return !!(document.body || document.documentElement);
        },

        isJQueryReady: () => {
            return $('body').length > 0 || $('html').length > 0;
        },

        setupAjax: (csrfToken) => {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });
        }
    };

    const validator = {
        username: (username) => {
            if (!username || username.length < 3) {
                return { valid: false, message: 'Username must be at least 3 characters' };
            }
            if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                return { valid: false, message: 'Username can only contain letters, numbers, and underscores' };
            }
            return { valid: true };
        },

        name: (name) => {
            if (!name || name.length < CONFIG.NAME_MIN_LENGTH) {
                return { valid: false, message: 'Name too short' };
            }
            return { valid: true };
        },

        email: (email) => {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                return { valid: false, message: 'Invalid email' };
            }
            return { valid: true };
        },

        password: (password) => {
            if (password.length < CONFIG.PASSWORD_MIN_LENGTH) {
                return { valid: false, message: `Password must be ${CONFIG.PASSWORD_MIN_LENGTH}+ chars` };
            }
            if (/^(password|123456|admin|user|test|p@ssw0rd)/i.test(password)) {
                return { valid: false, message: 'Weak password' };
            }
            return { valid: true };
        },

        passwordMatch: (password, confirm) => {
            if (password !== confirm) {
                return { valid: false, message: 'Passwords don\'t match' };
            }
            return { valid: true };
        },

        validateAll: (data) => {
            const validations = [
                validator.username(data.username),
                validator.name(data.name),
                validator.email(data.email),
                validator.password(data.password),
                validator.passwordMatch(data.password, data.confirm)
            ];

            for (const validation of validations) {
                if (!validation.valid) {
                    window.toast.error(validation.message);
                    return false;
                }
            }

            return true;
        }
    };

    class InstallWizard {
        constructor() {
            this.step = 2;
            this.data = {
                username: '',
                name: '',
                email: '',
                password: '',
                confirm: '',
                setup2fa: true,
                twoFactorSecret: '',
                recoveryCodes: [],
                app_name: '',
                app_url: '',
                timezone: 'Asia/Hong_Kong',
                demo: false
            };
            this.csrf = utils.getCSRFToken();
            this.session = utils.generateId();
        }

        getOrCreateSecret() {
            if (this.data.twoFactorSecret) {
                return this.data.twoFactorSecret.toString().trim();
            }

            try {
                const secret = generateSecret();
                this.data.twoFactorSecret = secret;
                return secret;
            } catch (e) {
                console.error('generateSecret failed', e);
                return '';
            }
        }

        generateRecoveryCodes(count = 10) {
            if (this.data.recoveryCodes && this.data.recoveryCodes.length > 0) {
                return this.data.recoveryCodes;
            }

            const codes = [];
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

            for (let i = 0; i < count; i++) {
                let code = '';
                for (let j = 0; j < 8; j++) {
                    code += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                codes.push(code);
            }

            this.data.recoveryCodes = codes;
            return codes;
        }

        async generateQRCode(secret, email, appName) {
            const safeSecret = (secret || this.getOrCreateSecret() || '').toString();
            if (!safeSecret) {
                console.error('2FA secret is missing; cannot generate QR code');
                return null;
            }

            this.data.twoFactorSecret = safeSecret;

            const resolvedEmail = email || this.data.email || 'admin@example.com';
            const resolvedAppName = appName || this.data.app_name || 'Jobs Board';
            this.data.email = resolvedEmail;
            this.data.app_name = resolvedAppName;

            const otpauth = generateURI({
                issuer: resolvedAppName,
                label: resolvedEmail,
                secret: safeSecret
            });

            try {
                return await QRCode.toDataURL(otpauth, {
                    width: 256,
                    margin: 2
                });
             } catch (error) {
                 console.error('Error generating QR code:', error);
                 return null;
             }
        }

        isAdminInfoComplete(formData) {
            if (!formData.username || formData.username.length < 3) return false;
            if (!formData.name || formData.name.length < CONFIG.NAME_MIN_LENGTH) return false;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(formData.email || '')) return false;
            if (!formData.password || formData.password.length < CONFIG.PASSWORD_MIN_LENGTH) return false;
            if (formData.password !== formData.confirm) return false;
            return true;
        }

        async ensure2FADisplay() {
            const $qrSection = $('#2fa-qr-section');
            if ($qrSection.length === 0) {
                return;
            }

            const formData = this.collectFormData();
            this.data = { ...this.data, ...formData };

            if (!this.isAdminInfoComplete(formData)) {
                $qrSection.slideUp(200);
                return;
            }

            if (!this.data.twoFactorSecret) {
                this.data.twoFactorSecret = this.getOrCreateSecret();
            }

            if (!this.data.twoFactorSecret) {
                console.error('2FA secret could not be generated');
                return;
            }

            if (!this.data.recoveryCodes || this.data.recoveryCodes.length === 0) {
                this.generateRecoveryCodes();
            }

            const email = this.data.email || 'admin@example.com';
            const appName = this.data.app_name || 'Jobs Board';

            const qrCodeDataUrl = await this.generateQRCode(this.data.twoFactorSecret, email, appName);

            if (qrCodeDataUrl) {
                $('#2fa-qr-code').attr('src', qrCodeDataUrl);
                const formattedSecret = (this.data.twoFactorSecret || '').replace(/\s+/g, '').match(/.{1,4}/g)?.join(' ') || this.data.twoFactorSecret;
                $('#2fa-secret-key').text(formattedSecret);
                
                const recoveryCodes = this.generateRecoveryCodes();
                this.renderRecoveryCodes(recoveryCodes);
                
                $qrSection.slideDown(300);
            }
        }

        init() {
            console.log('Initializing install wizard...');

            if (!utils.isDOMReady()) {
                console.error('Basic DOM not available, retrying...');
                setTimeout(() => this.init(), CONFIG.DELAYS.INIT);
                return;
            }

            if (!utils.isJQueryReady()) {
                console.error('jQuery cannot access DOM, retrying...');
                setTimeout(() => this.init(), CONFIG.DELAYS.INIT);
                return;
            }

            try {
                this.cleanupDOM();
                setTimeout(() => this.renderWizard(), CONFIG.DELAYS.RENDER);
            } catch (error) {
                console.error('DOM manipulation failed:', error);
                setTimeout(() => this.init(), CONFIG.DELAYS.INIT);
            }
        }

        cleanupDOM() {
            $('header, .text-gray-900').remove();
            $('main.mx-auto').removeClass('mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8');
        }

        renderWizard() {
            try {
                const $body = $('body');
                if ($body.length === 0) {
                    console.error('Body element not found');
                    return;
                }

                $body
                    .empty()
                    .removeClass()
                    .addClass('min-h-screen bg-gray-50')
                    .css({
                        display: 'block',
                        visibility: 'visible',
                        opacity: '1'
                    })
                    .html(this.getWizardHTML());

                if (window.toast) {
                    window.toast.container = null;
                    window.toast.init();
                }

                this.renderStep();
            } catch (error) {
                console.error('Wizard render failed:', error);
            }
        }

        getWizardHTML() {
            const stepsHTML = [2, 3].map(stepNum => this.getStepIndicatorHTML(stepNum)).join('');

            return `
                <div class="flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
                    <div class="max-w-3xl w-full space-y-8">
                        <div class="text-center">
                            <h1 class="text-4xl font-bold text-gray-900">Installation Wizard</h1>
                            <p class="mt-2 text-gray-600">Set up your Jobs Board</p>
                        </div>
                        <div class="flex justify-center space-x-4 mb-8">
                            ${stepsHTML}
                        </div>
                        <div class="bg-white rounded-lg shadow p-8" id="content"></div>
                    </div>
                </div>
            `;
        }

        getStepIndicatorHTML(stepNum) {
            const displayStepNum = stepNum - 1;
            const isActive = this.step >= stepNum;
            const bgClass = isActive ? 'bg-indigo-600 text-white' : 'bg-gray-300 text-gray-600';
            const textClass = isActive ? 'text-indigo-600' : 'text-gray-600';
            const label = CONFIG.STEP_LABELS[stepNum];

            return `
                <div class="text-center">
                    <div class="w-10 h-10 rounded-full mx-auto mb-2 ${bgClass} flex items-center justify-center">${displayStepNum}</div>
                    <div class="text-sm ${textClass}">${label}</div>
                </div>
            `;
        }

        renderStep() {
            const stepRenderers = {
                2: () => {
                    $(SELECTORS.CONTENT).html(this.renderStep2());
                    setTimeout(() => {
                        void this.ensure2FADisplay();
                        this.bindCopySecretHandler();
                        this.bindRecoveryCodeHandlers();
                        this.bindStep2FormHandler();
                        this.bindQrRefreshHandlers();
                        this.bindOtpTestHandler();
                    }, 100);
                },
                3: () => {
                    $(SELECTORS.CONTENT).html(this.renderStep3());
                }
            };

            const renderer = stepRenderers[this.step];
            if (renderer) {
                renderer();
            }
        }

        renderStep2() {
            return `
                <div class="space-y-8">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Admin Account Setup</h2>
                        <p class="text-gray-600 text-sm">Create your administrator account to manage the system</p>
                    </div>

                    <form id="install-step2-form" class="space-y-8">
                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Account Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Username <span class="text-red-500">*</span></label>
                                    <input id="username" type="text" placeholder="admin" required
                                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                                           value="${this.data.username || ''}">
                                    <p class="text-xs text-gray-500 mt-1">3+ characters, letters, numbers, and underscores only</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name <span class="text-red-500">*</span></label>
                                    <input id="name" type="text" placeholder="Alvin Wong" required
                                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                                           value="${this.data.name || ''}">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address <span class="text-red-500">*</span></label>
                                <input id="email" type="email" placeholder="admin@example.com" required
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                                       value="${this.data.email || ''}">
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Password <span class="text-red-500">*</span></label>
                                    <input id="password" type="password" placeholder="Minimum 12 characters" required
                                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                    <p class="text-xs text-gray-500 mt-1">Must be at least 12 characters long</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password <span class="text-red-500">*</span></label>
                                    <input id="confirm" type="password" placeholder="Re-enter password" required
                                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                </div>
                            </div>
                        </div>

                        <div class="bg-blue-50 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Security Settings</h3>
                            <p class="text-sm text-gray-700">Two-Factor Authentication (2FA) is required for the admin account.</p>

                            <div id="2fa-qr-section" class="mt-6 pt-6 border-t border-blue-200">
                                <div class="bg-white rounded-lg p-6 space-y-4">
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-800 mb-2">Scan QR Code</h4>
                                        <p class="text-xs text-gray-600 mb-4">Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)</p>
                                        <div class="flex justify-center bg-white p-4 rounded-lg border-2 border-gray-200">
                                            <img id="2fa-qr-code" src="" alt="2FA QR Code" class="w-48 h-48">
                                        </div>
                                    </div>

                                    <div class="pt-4 border-t">
                                        <h4 class="text-sm font-semibold text-gray-800 mb-2">Manual Entry Key</h4>
                                        <p class="text-xs text-gray-600 mb-3">If you can't scan the QR code, enter this key manually:</p>
                                        <div class="bg-gray-100 rounded-lg p-4">
                                            <code id="2fa-secret-key" class="text-sm font-mono text-gray-900 break-all select-all"></code>
                                        </div>
                                        <button type="button" id="copy-2fa-secret"
                                                class="mt-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                            Copy Secret Key
                                        </button>
                                    </div>

                                    <div class="pt-4 border-t space-y-2">
                                        <h4 class="text-sm font-semibold text-gray-800">Test Your 2FA Code</h4>
                                        <p class="text-xs text-gray-600">Enter the 6-digit code from your authenticator app to verify setup.</p>
                                        <div class="flex gap-2 items-center">
                                            <input id="test-otp" type="text" inputmode="numeric" maxlength="6" placeholder="123456"
                                                   class="w-32 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                                            <button type="button" id="test-otp-btn"
                                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors">
                                                Verify Code
                                            </button>
                                        </div>
                                        <p id="test-otp-result" class="text-xs"></p>
                                    </div>

                                    <div class="pt-4 border-t space-y-2">
                                        <h4 class="text-sm font-semibold text-gray-800">Recovery Codes</h4>
                                        <p class="text-xs text-gray-600">Save these recovery codes in a secure location. You can use them to access your account if you lose access to your authenticator device.</p>
                                        <div id="recovery-codes-container" class="bg-gray-100 rounded-lg p-4 space-y-2 hidden">
                                            <div id="recovery-codes-list" class="grid grid-cols-2 gap-2 font-mono text-sm"></div>
                                            <div class="flex gap-2 pt-2">
                                                <button type="button" id="copy-recovery-codes"
                                                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                                    Copy All Codes
                                                </button>
                                                <span class="text-gray-400">|</span>
                                                <button type="button" id="download-recovery-codes"
                                                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                                    Download as Text
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                        <p class="text-xs text-yellow-800">
                                            <strong>Important:</strong> Save this secret key and recovery codes in a secure location. You'll need them to set up 2FA on your authenticator app and to recover your account if you lose access to your device. The QR code will be available again after installation.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">System Configuration</h3>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Application Name</label>
                                <input id="app_name" type="text" placeholder="Jobs Board"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                                       value="${this.data.app_name || 'Jobs Board'}">
                                <p class="text-xs text-gray-500 mt-1">The name displayed throughout the application</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Application URL</label>
                                <input id="app_url" type="url" placeholder="https://example.com"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                                       value="${this.data.app_url || window.location.origin}">
                                <p class="text-xs text-gray-500 mt-1">The base URL of your application</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Timezone</label>
                                <select id="timezone" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition bg-white">
                                    <option value="Asia/Hong_Kong" ${this.data.timezone === 'Asia/Hong_Kong' ? 'selected' : ''}>Hong Kong (HKT)</option>
                                    <option value="UTC" ${this.data.timezone === 'UTC' ? 'selected' : ''}>UTC (Coordinated Universal Time)</option>
                                    <option value="Asia/Shanghai" ${this.data.timezone === 'Asia/Shanghai' ? 'selected' : ''}>Shanghai (CST)</option>
                                    <option value="Asia/Tokyo" ${this.data.timezone === 'Asia/Tokyo' ? 'selected' : ''}>Tokyo (JST)</option>
                                    <option value="Asia/Singapore" ${this.data.timezone === 'Asia/Singapore' ? 'selected' : ''}>Singapore (SGT)</option>
                                    <option value="Europe/London" ${this.data.timezone === 'Europe/London' ? 'selected' : ''}>London (GMT)</option>
                                    <option value="Europe/Paris" ${this.data.timezone === 'Europe/Paris' ? 'selected' : ''}>Paris (CET)</option>
                                    <option value="America/New_York" ${this.data.timezone === 'America/New_York' ? 'selected' : ''}>New York (EST)</option>
                                    <option value="America/Los_Angeles" ${this.data.timezone === 'America/Los_Angeles' ? 'selected' : ''}>Los Angeles (PST)</option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Select your local timezone for accurate date and time display</p>
                            </div>
                        </div>

                        <div class="flex gap-4 pt-4 border-t">
                            <button type="button" onclick="window.installWizard.prev()"
                                    class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                                Back
                            </button>
                            <button type="submit"
                                    class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors shadow-sm">
                                Continue to Review
                            </button>
                        </div>
                    </form>
                </div>
            `;
        }

        renderStep3() {
            return `
                <div class="space-y-8">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Review & Complete</h2>
                        <p class="text-gray-600 text-sm">Review your settings before completing the installation</p>
                    </div>

                    <div class="space-y-6">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                            <label class="flex items-start cursor-pointer group">
                                <input id="demo" type="checkbox" class="mt-1 mr-3 w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer" ${this.data.demo ? 'checked' : ''}>
                                <div class="flex-1">
                                    <span class="block font-medium text-gray-900">Install Demo Data</span>
                                    <span class="block text-sm text-gray-600 mt-1">Include sample jobs, companies, and categories to help you get started</span>
                                </div>
                            </label>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-3 border-b">Admin Account</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Username</span>
                                    <p class="text-gray-900 font-medium mt-1">${this.data.username}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Full Name</span>
                                    <p class="text-gray-900 font-medium mt-1">${this.data.name}</p>
                                </div>
                                <div class="md:col-span-2">
                                    <span class="text-sm font-medium text-gray-500">Email Address</span>
                                    <p class="text-gray-900 font-medium mt-1">${this.data.email}</p>
                                </div>
                                <div class="md:col-span-2">
                                    <span class="text-sm font-medium text-gray-500">Two-Factor Authentication</span>
                                    <p class="mt-1">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                            ✓ Enabled (required)
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-3 border-b">System Configuration</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Application Name</span>
                                    <p class="text-gray-900 font-medium mt-1">${this.data.app_name || 'Jobs Board'}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Timezone</span>
                                    <p class="text-gray-900 font-medium mt-1">${this.data.timezone}</p>
                                </div>
                                <div class="md:col-span-2">
                                    <span class="text-sm font-medium text-gray-500">Application URL</span>
                                    <p class="text-gray-900 font-medium mt-1 break-all">${this.data.app_url || window.location.origin}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-4 pt-4 border-t">
                        <button onclick="window.installWizard.prev()"
                                class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                            Back to Edit
                        </button>
                        <button onclick="window.installWizard.complete()"
                                class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors shadow-sm">
                            Complete Installation
                        </button>
                    </div>
                </div>
            `;
        }

        collectFormData() {
            return {
                username: $(SELECTORS.USERNAME).val().trim().replace(/[<>'"&]/g, ''),
                name: $(SELECTORS.NAME).val().trim().replace(/[<>'"&]/g, ''),
                email: $(SELECTORS.EMAIL).val().trim(),
                password: $(SELECTORS.PASSWORD).val(),
                confirm: $(SELECTORS.CONFIRM).val(),
                setup2fa: true,
                twoFactorSecret: this.data.twoFactorSecret || '',
                app_name: $(SELECTORS.APP_NAME).val().trim() || 'Jobs Board',
                app_url: $(SELECTORS.APP_URL).val().trim() || window.location.origin,
                timezone: $(SELECTORS.TIMEZONE).val() || 'Asia/Hong_Kong'
            };
        }

        next() {
            if (this.step === 2) {
                this.data = this.collectFormData();
                if (!validator.validateAll(this.data)) {
                    return;
                }
            }

            if (this.step < 3) {
                this.step++;
                this.renderStep();
            }
        }

        prev() {
            if (this.step > 2) {
                this.step--;
                this.renderStep();
            }
        }

        renderRecoveryCodes(codes) {
            const $container = $('#recovery-codes-container');
            const $list = $('#recovery-codes-list');
            
            if (!$container.length || !$list.length || !codes || codes.length === 0) {
                return;
            }

            $list.empty();
            codes.forEach((code, index) => {
                $list.append(`<div class="text-gray-900">${index + 1}. ${code}</div>`);
            });
            
            $container.removeClass('hidden');
        }

        bindCopySecretHandler() {
            const $button = $('#copy-2fa-secret');
            $button.off('click').on('click', () => {
                const secret = $('#2fa-secret-key').text().replace(/\s/g, '');
                if (!secret) {
                    window.toast.error('Nothing to copy.');
                    return;
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(secret)
                        .then(() => window.toast.success('Secret key copied to clipboard!'))
                        .catch(() => window.toast.error('Failed to copy secret key.'));
                } else {
                    window.toast.error('Clipboard not available in this browser.');
                }
            });
        }

        bindRecoveryCodeHandlers() {
            const $copyBtn = $('#copy-recovery-codes');
            const $downloadBtn = $('#download-recovery-codes');

            $copyBtn.off('click').on('click', () => {
                const codes = this.data.recoveryCodes || [];
                if (codes.length === 0) {
                    window.toast.error('No recovery codes available.');
                    return;
                }

                const codesText = codes.join('\n');
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(codesText)
                        .then(() => window.toast.success('Recovery codes copied to clipboard!'))
                        .catch(() => window.toast.error('Failed to copy recovery codes.'));
                } else {
                    window.toast.error('Clipboard not available in this browser.');
                }
            });

            $downloadBtn.off('click').on('click', () => {
                const codes = this.data.recoveryCodes || [];
                if (codes.length === 0) {
                    window.toast.error('No recovery codes available.');
                    return;
                }

                const appName = this.data.app_name || 'Jobs Board';
                const email = this.data.email || 'admin@example.com';
                const content = [
                    `${appName} - Recovery Codes`,
                    `Generated for: ${email}`,
                    `Generated on: ${new Date().toLocaleString()}`,
                    '',
                    'IMPORTANT: Store these codes in a secure location.',
                    'Each code can only be used once.',
                    '',
                    'Recovery Codes:',
                    ...codes.map((code, index) => `${index + 1}. ${code}`),
                    '',
                    'If you lose access to your authenticator device, use one of these codes to log in.',
                    'After using a code, it will be invalidated and cannot be used again.'
                ].join('\n');

                const blob = new Blob([content], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${appName.replace(/\s+/g, '_')}_recovery_codes_${Date.now()}.txt`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                window.toast.success('Recovery codes downloaded!');
            });
        }

        bindStep2FormHandler() {
            const $form = $('#install-step2-form');
            $form.off('submit').on('submit', (e) => {
                e.preventDefault();
                this.next();
            });
        }

        bindQrRefreshHandlers() {
            const refresh = () => {
                void this.ensure2FADisplay();
            };

            $(SELECTORS.USERNAME).off('input.qr').on('input.qr', refresh);
            $(SELECTORS.NAME).off('input.qr').on('input.qr', refresh);
            $(SELECTORS.EMAIL).off('input.qr').on('input.qr', refresh);
            $(SELECTORS.PASSWORD).off('input.qr').on('input.qr', refresh);
            $(SELECTORS.CONFIRM).off('input.qr').on('input.qr', refresh);
            $(SELECTORS.APP_NAME).off('input.qr').on('input.qr', refresh);
        }

        bindOtpTestHandler() {
            const $btn = $('#test-otp-btn');
            const $input = $('#test-otp');
            const $result = $('#test-otp-result');

            if (!$btn.length || !$input.length) {
                return;
            }

            const setResult = (message, success) => {
                $result.text(message)
                    .removeClass('text-red-600 text-green-600')
                    .addClass(success ? 'text-green-600' : 'text-red-600');
            };

            $btn.off('click.otp').on('click.otp', async () => {
                const code = ($input.val() || '').toString().trim();
                if (!/^\d{6}$/.test(code)) {
                    setResult('Enter the 6-digit code from your authenticator app.', false);
                    return;
                }

                const secret = (this.data.twoFactorSecret || this.getOrCreateSecret() || '').toString().trim();
                if (!secret) {
                    setResult('2FA secret is missing. Please refresh the page.', false);
                    return;
                }

                try {
                    const result = await verify({ token: code, secret });
                    if (result && result.valid === true) {
                        setResult('Code is valid. 2FA setup looks good!', true);
                    } else {
                        setResult('Invalid code. Please double-check your authenticator app.', false);
                    }
                } catch (error) {
                    console.error('Error verifying OTP:', error);
                    setResult('Invalid code. Please double-check your authenticator app.', false);
                }
             });
        }

        complete() {
            try {
                this.data.demo = $(SELECTORS.DEMO).is(':checked');

                if (!validator.validateAll(this.data)) {
                    return;
                }

                if (!this.csrf) {
                    window.toast.error('Security token missing. Please refresh the page.');
                    return;
                }

                utils.setupAjax(this.csrf);

                // Ensure recovery codes are generated
                if (!this.data.recoveryCodes || this.data.recoveryCodes.length === 0) {
                    this.generateRecoveryCodes();
                }

                const payload = {
                    ...this.data,
                    admin_name: this.data.name,
                    admin_email: this.data.email,
                    admin_password: this.data.password,
                    admin_password_confirmation: this.data.confirm,
                    two_factor_secret: this.data.twoFactorSecret,
                    recovery_codes: this.data.recoveryCodes || [],
                    timestamp: Date.now(),
                    session: this.session
                };

                $.post(ENDPOINTS.COMPLETE, payload)
                .done(data => {
                    window.location.href = (data && data.success && data.redirect)
                        ? data.redirect
                        : '/login';
                })
                .fail((xhr, status, error) => {
                    console.error('Installation failed:', error);
                    window.toast.error('Installation failed. Please check the console for details.');
                });
            } catch (error) {
                console.error('Complete function error:', error);
                window.toast.error('An error occurred. Please refresh and try again.');
            }
        }
    }

    const initializeWizard = () => {
        console.log('Document ready, checking install page...');

        if (!utils.isInstallPage()) {
            console.log('Not on install page, exiting');
            return;
        }

        if (!utils.isDOMReady()) {
            console.error('DOM not available, exiting');
            return;
        }

        console.log('Install page confirmed, initializing...');
        const wizard = new InstallWizard();
        window.installWizard = wizard;

        console.log('Starting wizard with delay...');
        setTimeout(() => wizard.init(), CONFIG.DELAYS.INIT);
    };

    $(document).ready(initializeWizard);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeWizard);
    } else if (document.readyState === 'interactive' || document.readyState === 'complete') {
        console.log('Document already ready, initializing immediately...');
        if (utils.isInstallPage() && utils.isDOMReady()) {
            const wizard = new InstallWizard();
            window.installWizard = wizard;
            setTimeout(() => wizard.init(), 10);
        }
    }
})();
