import QRCode from "qrcode";
import { generateSecret, generateURI, verify } from "otplib";

$(() => {
    "use strict";

    const CONFIG = {
        INSTALL_PATH: "/install",
        DELAYS: { INIT: 50, RENDER: 10, FALLBACK: 100 },
        PASSWORD_MIN_LENGTH: 12,
        NAME_MIN_LENGTH: 2,
        STEP_LABELS: { 1: "Account", 2: "System", 3: "Security", 4: "Review" },
    };

    const SELECTORS = {
        CSRF_TOKEN: 'meta[name="csrf-token"]',
        CONTENT: "#content",
        USERNAME: "#username",
        NAME: "#name",
        EMAIL: "#email",
        PASSWORD: "#password",
        CONFIRM: "#confirm",
        DEMO: "#demo",
        APP_NAME: "#app_name",
        APP_URL: "#app_url",
        TIMEZONE: "#timezone",
    };

    const ENDPOINTS = { COMPLETE: "/install/complete" };

    const utils = {
        generateId: () =>
            crypto?.randomUUID?.() ||
            `install_${Date.now()}_${Math.random().toString(36).slice(2, 11)}`,
        getCSRFToken: () => $(SELECTORS.CSRF_TOKEN).attr("content") || "",
        isInstallPage: () => window.location.pathname === CONFIG.INSTALL_PATH,
        isDOMReady: () => !!(document.body || document.documentElement),
        isJQueryReady: () => $("body").length > 0 || $("html").length > 0,
        setupAjax: (csrfToken) =>
            $.ajaxSetup({ headers: { "X-CSRF-TOKEN": csrfToken } }),
    };

    const validator = {
        username: (username) =>
            !username || username.length < 3
                ? {
                    valid: false,
                    message: "Username must be at least 3 characters",
                }
                : !/^[a-zA-Z0-9_]+$/.test(username)
                    ? {
                        valid: false,
                        message:
                            "Username can only contain letters, numbers, and underscores",
                    }
                    : { valid: true },
        name: (name) =>
            !name || name.length < CONFIG.NAME_MIN_LENGTH
                ? { valid: false, message: "Name too short" }
                : { valid: true },
        email: (email) =>
            !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
                ? { valid: false, message: "Invalid email" }
                : { valid: true },
        password: (password) =>
            password.length < CONFIG.PASSWORD_MIN_LENGTH
                ? {
                    valid: false,
                    message: `Password must be ${CONFIG.PASSWORD_MIN_LENGTH}+ chars`,
                }
                : /^(password|123456|admin|user|test|p@ssw0rd)/i.test(password)
                    ? { valid: false, message: "Weak password" }
                    : { valid: true },
        passwordMatch: (password, confirm) =>
            password !== confirm
                ? { valid: false, message: "Passwords don't match" }
                : { valid: true },
        validateStep1: (data) => {
            const validations = [
                validator.username(data.username),
                validator.name(data.name),
                validator.email(data.email),
                validator.password(data.password),
                validator.passwordMatch(data.password, data.confirm),
            ];
            for (const validation of validations)
                if (!validation.valid) {
                    window.toast.error(validation.message);
                    return false;
                }
            return true;
        },
    };

    class InstallWizard {
        constructor() {
            this.step = 1;
            this.data = {
                username: "",
                name: "",
                email: "",
                password: "",
                confirm: "",
                setup2fa: true,
                twoFactorSecret: "",
                recoveryCodes: [],
                app_name: "Jobs Board",
                app_url: window.location.origin,
                timezone: "Asia/Hong_Kong",
                demo: false,
            };
            this.csrf = utils.getCSRFToken();
            this.session = utils.generateId();
        }

        getOrCreateSecret() {
            if (this.data.twoFactorSecret)
                return this.data.twoFactorSecret.toString().trim();
            try {
                const secret = generateSecret();
                this.data.twoFactorSecret = secret;
                return secret;
            } catch (e) {
                console.error("generateSecret failed", e);
                return "";
            }
        }

        generateRecoveryCodes(count = 10) {
            if (this.data.recoveryCodes?.length > 0)
                return this.data.recoveryCodes;
            const codes = [],
                chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
            for (let i = 0; i < count; i++) {
                let code = "";
                for (let j = 0; j < 8; j++)
                    code += chars.charAt(
                        Math.floor(Math.random() * chars.length),
                    );
                codes.push(code);
            }
            this.data.recoveryCodes = codes;
            return codes;
        }

        async generateQRCode(secret, email, appName) {
            const safeSecret = (
                secret ||
                this.getOrCreateSecret() ||
                ""
            ).toString();
            if (!safeSecret) {
                console.error("2FA secret is missing; cannot generate QR code");
                return null;
            }
            this.data.twoFactorSecret = safeSecret;
            const resolvedEmail =
                email || this.data.email || "admin@example.com";
            const resolvedAppName =
                appName || this.data.app_name || "Jobs Board";
            const otpauth = generateURI({
                issuer: resolvedAppName,
                label: resolvedEmail,
                secret: safeSecret,
            });
            try {
                return await QRCode.toDataURL(otpauth, {
                    width: 256,
                    margin: 2,
                });
            } catch (error) {
                console.error("Error generating QR code:", error);
                return null;
            }
        }

        init() {
            console.log("Initializing install wizard...");
            if (!utils.isDOMReady()) {
                console.error("Basic DOM not available, retrying...");
                setTimeout(() => this.init(), CONFIG.DELAYS.INIT);
                return;
            }
            if (!utils.isJQueryReady()) {
                console.error("jQuery cannot access DOM, retrying...");
                setTimeout(() => this.init(), CONFIG.DELAYS.INIT);
                return;
            }
            try {
                this.cleanupDOM();
                setTimeout(() => this.renderWizard(), CONFIG.DELAYS.RENDER);
            } catch (error) {
                console.error("DOM manipulation failed:", error);
                setTimeout(() => this.init(), CONFIG.DELAYS.INIT);
            }
        }

        cleanupDOM() {
            $("header, .text-gray-900").remove();
            $("main.mx-auto").removeClass(
                "mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8",
            );
        }

        renderWizard() {
            try {
                const $body = $("body");
                if (!$body.length) {
                    console.error("Body element not found");
                    return;
                }
                $body
                    .empty()
                    .removeClass()
                    .addClass(
                        "min-h-screen bg-gradient-to-br from-indigo-50 to-blue-50",
                    )
                    .css({
                        display: "block",
                        visibility: "visible",
                        opacity: "1",
                    })
                    .html(this.getWizardHTML());
                if (window.toast) {
                    window.toast.container = null;
                    window.toast.init();
                }
                this.renderStep();
            } catch (error) {
                console.error("Wizard render failed:", error);
            }
        }

        getWizardHTML() {
            const stepsHTML = [1, 2, 3, 4]
                .map((stepNum) => this.getStepIndicatorHTML(stepNum))
                .join("");
            return `<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8"><div class="max-w-2xl w-full"><div class="text-center mb-8"><div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-600 rounded-2xl mb-4 shadow-lg"><svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg></div><h1 class="text-3xl font-bold text-gray-900 mb-2">Setup Your Job Board</h1><p class="text-gray-600">Let's get everything configured in just a few steps</p></div><div class="flex justify-between mb-8 max-w-md mx-auto">${stepsHTML}</div><div class="bg-white rounded-2xl shadow-xl p-8" id="content"></div></div></div>`;
        }

        getStepIndicatorHTML(stepNum) {
            const isActive = this.step === stepNum,
                isComplete = this.step > stepNum,
                label = CONFIG.STEP_LABELS[stepNum];
            let classes, content;
            if (isComplete) {
                classes = "bg-green-500 text-white border-green-500";
                content = `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>`;
            } else if (isActive) {
                classes =
                    "bg-indigo-600 text-white border-indigo-600 ring-4 ring-indigo-200";
                content = stepNum;
            } else {
                classes = "bg-white text-gray-400 border-gray-300";
                content = stepNum;
            }
            return `<div class="flex flex-col items-center step-indicator"><div class="step-circle w-10 h-10 rounded-full border-2 ${classes} flex items-center justify-center font-semibold text-sm transition-all duration-200">${content}</div><span class="step-label text-xs mt-2 font-medium ${isActive ? "text-indigo-600" : isComplete ? "text-green-600" : "text-gray-400"}">${label}</span></div>`;
        }

        renderStep() {
            const stepRenderers = {
                1: () => {
                    $(SELECTORS.CONTENT).html(this.renderStep1());
                    this.bindStep1FormHandler();
                },
                2: () => {
                    $(SELECTORS.CONTENT).html(this.renderStep2());
                    this.bindStep2FormHandler();
                },
                3: () => {
                    $(SELECTORS.CONTENT).html(this.renderStep3());
                    setTimeout(() => {
                        void this.setup2FA();
                        this.bindCopySecretHandler();
                        this.bindRecoveryCodeHandlers();
                        this.bindOtpTestHandler();
                        this.bindStep3FormHandler();
                    }, 100);
                },
                4: () => {
                    $(SELECTORS.CONTENT).html(this.renderStep4());
                },
            };
            const renderer = stepRenderers[this.step];
            if (renderer) renderer();
        }

        renderStep1() {
            return `
                <div class="space-y-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Create Admin Account</h2>
                        <p class="text-sm text-gray-600 mt-1">This account will have full access to manage your job board</p>
                    </div>

                    <form id="step1-form" class="space-y-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Username <span class="text-red-500">*</span></label>
                                <input id="username" type="text" placeholder="admin" required
                                       class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                       value="${this.data.username || ""}">
                                <p class="text-xs text-gray-500 mt-1">Letters, numbers, underscores (3+ chars)</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                                <input id="name" type="text" placeholder="John Doe" required
                                       class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                       value="${this.data.name || ""}">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address <span class="text-red-500">*</span></label>
                            <input id="email" type="email" placeholder="admin@example.com" required
                                   class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                   value="${this.data.email || ""}">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Password <span class="text-red-500">*</span></label>
                                <input id="password" type="password" placeholder="Min 12 characters" required
                                       class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <p class="text-xs text-gray-500 mt-1">Use a strong, unique password</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password <span class="text-red-500">*</span></label>
                                <input id="confirm" type="password" placeholder="Re-enter password" required
                                       class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="submit"
                                    class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors shadow-sm">
                                Continue →
                            </button>
                        </div>
                    </form>
                </div>
            `;
        }

        renderStep2() {
            return `
                <div class="space-y-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">System Configuration</h2>
                        <p class="text-sm text-gray-600 mt-1">Customize your application settings</p>
                    </div>

                    <form id="step2-form" class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Application Name</label>
                            <input id="app_name" type="text" placeholder="My Job Board"
                                   class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                   value="${this.data.app_name || "Jobs Board"}">
                            <p class="text-xs text-gray-500 mt-1">Shown in headers and emails</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Application URL</label>
                            <input id="app_url" type="url" placeholder="https://jobs.example.com"
                                   class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                   value="${this.data.app_url || window.location.origin}">
                            <p class="text-xs text-gray-500 mt-1">Your site's base URL</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Timezone</label>
                            <select id="timezone" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                                <option value="Asia/Hong_Kong" ${this.data.timezone === "Asia/Hong_Kong" ? "selected" : ""}>Hong Kong (HKT)</option>
                                <option value="UTC" ${this.data.timezone === "UTC" ? "selected" : ""}>UTC</option>
                                <option value="Asia/Shanghai" ${this.data.timezone === "Asia/Shanghai" ? "selected" : ""}>Shanghai (CST)</option>
                                <option value="Asia/Tokyo" ${this.data.timezone === "Asia/Tokyo" ? "selected" : ""}>Tokyo (JST)</option>
                                <option value="Asia/Singapore" ${this.data.timezone === "Asia/Singapore" ? "selected" : ""}>Singapore (SGT)</option>
                                <option value="Europe/London" ${this.data.timezone === "Europe/London" ? "selected" : ""}>London (GMT)</option>
                                <option value="Europe/Paris" ${this.data.timezone === "Europe/Paris" ? "selected" : ""}>Paris (CET)</option>
                                <option value="America/New_York" ${this.data.timezone === "America/New_York" ? "selected" : ""}>New York (EST)</option>
                                <option value="America/Los_Angeles" ${this.data.timezone === "America/Los_Angeles" ? "selected" : ""}>Los Angeles (PST)</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">All timestamps will use this timezone</p>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="window.installWizard.prev()"
                                    class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition-colors">
                                ← Back
                            </button>
                            <button type="submit"
                                    class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors shadow-sm">
                                Continue →
                            </button>
                        </div>
                    </form>
                </div>
            `;
        }

        renderStep3() {
            return `
                <div class="space-y-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Two-Factor Authentication</h2>
                        <p class="text-sm text-gray-600 mt-1">Secure your admin account with 2FA (required for administrators)</p>
                    </div>

                    <form id="step3-form" class="space-y-5">
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 space-y-5">
                            <div class="text-center">
                                <div class="inline-block bg-white p-4 rounded-xl shadow-sm">
                                    <img id="2fa-qr-code" src="" alt="2FA QR Code" class="w-48 h-48">
                                </div>
                                <p class="text-sm text-gray-700 mt-3">Scan with Google Authenticator, Authy, or similar app</p>
                            </div>

                            <div class="bg-white rounded-lg p-4">
                                <label class="block text-xs font-semibold text-gray-700 mb-2">Manual Entry Key</label>
                                <div class="flex items-center gap-2">
                                    <code id="2fa-secret-key" class="flex-1 text-sm font-mono bg-gray-50 px-3 py-2 rounded border text-gray-900"></code>
                                    <button type="button" id="copy-2fa-secret"
                                            class="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded transition-colors">
                                        Copy
                                    </button>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg p-4 space-y-3">
                                <label class="block text-xs font-semibold text-gray-700">Test Your Code</label>
                                <div class="flex gap-2">
                                    <input id="test-otp" type="text" inputmode="numeric" maxlength="6" placeholder="123456"
                                           class="w-32 px-3 py-2 text-center text-lg font-mono border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" />
                                    <button type="button" id="test-otp-btn"
                                            class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg font-medium hover:bg-indigo-700">
                                        Verify
                                    </button>
                                </div>
                                <p id="test-otp-result" class="text-xs"></p>
                            </div>
                        </div>

                        <div class="bg-amber-50 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-gray-900 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                                Recovery Codes
                            </h4>
                            <p class="text-xs text-gray-700 mb-3">Save these codes securely. Use them if you lose access to your authenticator.</p>
                            <div id="recovery-codes-container" class="hidden">
                                <div id="recovery-codes-list" class="grid grid-cols-2 gap-1.5 font-mono text-xs mb-3"></div>
                                <div class="flex gap-3 text-xs">
                                    <button type="button" id="copy-recovery-codes" class="text-indigo-600 hover:text-indigo-800 font-medium">Copy All</button>
                                    <button type="button" id="download-recovery-codes" class="text-indigo-600 hover:text-indigo-800 font-medium">Download</button>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="window.installWizard.prev()"
                                    class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition-colors">
                                ← Back
                            </button>
                            <button type="submit"
                                    class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors shadow-sm">
                                Continue →
                            </button>
                        </div>
                    </form>
                </div>
            `;
        }

        renderStep4() {
            return `
                <div class="space-y-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Review & Complete</h2>
                        <p class="text-sm text-gray-600 mt-1">Everything looks good? Let's finish the setup!</p>
                    </div>

                    <div class="space-y-4">
                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg p-5 border border-green-200">
                            <label class="flex items-start cursor-pointer group">
                                <input id="demo" type="checkbox" class="mt-0.5 mr-3 w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" ${this.data.demo ? "checked" : ""}>
                                <div>
                                    <span class="block font-medium text-gray-900">Include Demo Data</span>
                                    <span class="block text-sm text-gray-600 mt-0.5">Add sample jobs and applications to explore features</span>
                                </div>
                            </label>
                        </div>

                        <div class="bg-white border rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-5 py-3 border-b">
                                <h3 class="font-semibold text-gray-900 text-sm">Admin Account</h3>
                            </div>
                            <div class="p-5 space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Username</span>
                                    <span class="font-medium text-gray-900">${this.data.username}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Name</span>
                                    <span class="font-medium text-gray-900">${this.data.name}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Email</span>
                                    <span class="font-medium text-gray-900">${this.data.email}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">2FA</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">✓ Enabled</span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white border rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-5 py-3 border-b">
                                <h3 class="font-semibold text-gray-900 text-sm">System Settings</h3>
                            </div>
                            <div class="p-5 space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">App Name</span>
                                    <span class="font-medium text-gray-900">${this.data.app_name}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Timezone</span>
                                    <span class="font-medium text-gray-900">${this.data.timezone}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">URL</span>
                                    <span class="font-medium text-gray-900 truncate max-w-xs">${this.data.app_url}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button onclick="window.installWizard.prev()"
                                class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition-colors">
                            ← Back
                        </button>
                        <button onclick="window.installWizard.complete()"
                                class="flex-1 px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg font-medium hover:from-indigo-700 hover:to-purple-700 transition-all shadow-lg">
                            Complete Installation ✓
                        </button>
                    </div>
                </div>
            `;
        }

        async setup2FA() {
            if (!this.data.twoFactorSecret)
                this.data.twoFactorSecret = this.getOrCreateSecret();
            if (!this.data.recoveryCodes?.length) this.generateRecoveryCodes();
            const qrCodeDataUrl = await this.generateQRCode(
                this.data.twoFactorSecret,
                this.data.email,
                this.data.app_name,
            );
            if (qrCodeDataUrl) {
                $("#2fa-qr-code").attr("src", qrCodeDataUrl);
                const formattedSecret =
                    (this.data.twoFactorSecret || "")
                        .replace(/\s+/g, "")
                        .match(/.{1,4}/g)
                        ?.join(" ") || this.data.twoFactorSecret;
                $("#2fa-secret-key").text(formattedSecret);
                this.renderRecoveryCodes(this.data.recoveryCodes);
            }
        }

        collectFormData() {
            const result = {};
            const $username = $(SELECTORS.USERNAME);
            const $name = $(SELECTORS.NAME);
            const $email = $(SELECTORS.EMAIL);
            const $password = $(SELECTORS.PASSWORD);
            const $confirm = $(SELECTORS.CONFIRM);
            const $appName = $(SELECTORS.APP_NAME);
            const $appUrl = $(SELECTORS.APP_URL);
            const $timezone = $(SELECTORS.TIMEZONE);

            if ($username.length) result.username = $username.val()?.trim().replace(/[<>'"&]/g, "") || "";
            if ($name.length) result.name = $name.val()?.trim().replace(/[<>'"&]/g, "") || "";
            if ($email.length) result.email = $email.val()?.trim() || "";
            if ($password.length) result.password = $password.val() || "";
            if ($confirm.length) result.confirm = $confirm.val() || "";
            if ($appName.length) result.app_name = $appName.val()?.trim() || "Jobs Board";
            if ($appUrl.length) result.app_url = $appUrl.val()?.trim() || window.location.origin;
            if ($timezone.length) result.timezone = $timezone.val() || "Asia/Hong_Kong";

            return result;
        }

        bindStep1FormHandler() {
            $("#step1-form")
                .off("submit")
                .on("submit", (e) => {
                    e.preventDefault();
                    const formData = this.collectFormData();
                    if (validator.validateStep1(formData)) {
                        this.data = { ...this.data, ...formData };
                        this.next();
                    }
                });
        }
        bindStep2FormHandler() {
            $("#step2-form")
                .off("submit")
                .on("submit", (e) => {
                    e.preventDefault();
                    const formData = this.collectFormData();
                    this.data = { ...this.data, ...formData };
                    this.next();
                });
        }
        bindStep3FormHandler() {
            $("#step3-form")
                .off("submit")
                .on("submit", (e) => {
                    e.preventDefault();
                    this.next();
                });
        }

        next() {
            if (this.step < 4) {
                this.step++;
                this.updateStepIndicators();
                this.renderStep();
            }
        }
        prev() {
            if (this.step > 1) {
                this.step--;
                this.updateStepIndicators();
                this.renderStep();
            }
        }

        updateStepIndicators() {
            const $indicators = $(".step-indicator");
            $indicators.each((index, el) => {
                const stepNum = index + 1;
                const $circle = $(el).find(".step-circle");
                const $label = $(el).find(".step-label");

                // Update circle
                $circle.removeClass("bg-green-500 text-white border-green-500 bg-indigo-600 border-indigo-600 ring-4 ring-indigo-200 bg-white text-gray-400 border-gray-300");

                if (this.step > stepNum) {
                    $circle.addClass("bg-green-500 text-white border-green-500");
                    $circle.html('<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>');
                } else if (this.step === stepNum) {
                    $circle.addClass("bg-indigo-600 text-white border-indigo-600 ring-4 ring-indigo-200");
                    $circle.html(stepNum);
                } else {
                    $circle.addClass("bg-white text-gray-400 border-gray-300");
                    $circle.html(stepNum);
                }

                // Update label
                $label.removeClass("text-indigo-600 text-green-600 text-gray-400");
                if (this.step === stepNum) {
                    $label.addClass("text-indigo-600");
                } else if (this.step > stepNum) {
                    $label.addClass("text-green-600");
                } else {
                    $label.addClass("text-gray-400");
                }
            });
        }

        renderRecoveryCodes(codes) {
            const $container = $("#recovery-codes-container"),
                $list = $("#recovery-codes-list");
            if (!$container.length || !$list.length || !codes?.length) return;
            $list.empty();
            codes.forEach((code) =>
                $list.append(
                    `<div class="text-gray-700 bg-white px-2 py-1 rounded">${code}</div>`,
                ),
            );
            $container.removeClass("hidden");
        }

        bindCopySecretHandler() {
            $("#copy-2fa-secret")
                .off("click")
                .on("click", () => {
                    const secret = $("#2fa-secret-key")
                        .text()
                        .replace(/\s/g, "");
                    if (!secret) {
                        window.toast.error("Nothing to copy.");
                        return;
                    }
                    if (navigator.clipboard?.writeText)
                        navigator.clipboard
                            .writeText(secret)
                            .then(() => window.toast.success("Secret copied!"))
                            .catch(() => window.toast.error("Failed to copy."));
                    else window.toast.error("Clipboard not available.");
                });
        }

        bindRecoveryCodeHandlers() {
            $("#copy-recovery-codes")
                .off("click")
                .on("click", () => {
                    const codes = this.data.recoveryCodes || [];
                    if (!codes.length) {
                        window.toast.error("No recovery codes available.");
                        return;
                    }
                    const codesText = codes.join("\n");
                    if (navigator.clipboard?.writeText)
                        navigator.clipboard
                            .writeText(codesText)
                            .then(() => window.toast.success("Codes copied!"))
                            .catch(() => window.toast.error("Failed to copy."));
                });

            $("#download-recovery-codes")
                .off("click")
                .on("click", () => {
                    const codes = this.data.recoveryCodes || [];
                    if (!codes.length) {
                        window.toast.error("No recovery codes.");
                        return;
                    }
                    const content = [
                        `${this.data.app_name} - Recovery Codes`,
                        `Generated for: ${this.data.email}`,
                        `Generated on: ${new Date().toLocaleString()}`,
                        "",
                        "IMPORTANT: Store these codes securely.",
                        "Each code can only be used once.",
                        "",
                        "Recovery Codes:",
                        ...codes.map((code, i) => `${i + 1}. ${code}`),
                    ].join("\n");
                    const blob = new Blob([content], { type: "text/plain" }),
                        url = URL.createObjectURL(blob),
                        a = document.createElement("a");
                    a.href = url;
                    a.download = `recovery_codes_${Date.now()}.txt`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    window.toast.success("Codes downloaded!");
                });
        }

        bindOtpTestHandler() {
            const $btn = $("#test-otp-btn"),
                $input = $("#test-otp"),
                $result = $("#test-otp-result");
            if (!$btn.length || !$input.length) return;
            const setResult = (message, success) =>
                $result
                    .text(message)
                    .removeClass("text-red-600 text-green-600")
                    .addClass(success ? "text-green-600" : "text-red-600");
            $btn.off("click").on("click", async () => {
                const code = ($input.val() || "").toString().trim();
                if (!/^\d{6}$/.test(code)) {
                    setResult("Enter a 6-digit code.", false);
                    return;
                }
                const secret = (this.data.twoFactorSecret || "")
                    .toString()
                    .trim();
                if (!secret) {
                    setResult("2FA secret missing. Please refresh.", false);
                    return;
                }
                try {
                    const result = await verify({ token: code, secret });
                    setResult(
                        result?.valid
                            ? "✓ Valid code! 2FA is working."
                            : "Invalid code. Check your app.",
                        result?.valid,
                    );
                } catch (error) {
                    console.error("OTP verification error:", error);
                    setResult("Invalid code. Check your app.", false);
                }
            });
        }

        complete() {
            try {
                this.data.demo = $(SELECTORS.DEMO).is(":checked");
                if (!this.csrf) {
                    window.toast.error(
                        "Security token missing. Refresh the page.",
                    );
                    return;
                }
                utils.setupAjax(this.csrf);
                if (!this.data.recoveryCodes?.length)
                    this.generateRecoveryCodes();
                const payload = {
                    ...this.data,
                    admin_name: this.data.name,
                    admin_email: this.data.email,
                    admin_password: this.data.password,
                    admin_password_confirmation: this.data.confirm,
                    two_factor_secret: this.data.twoFactorSecret,
                    recovery_codes: this.data.recoveryCodes || [],
                    timestamp: Date.now(),
                    session: this.session,
                };
                window.toast.info("Setting up your job board...");
                $.post(ENDPOINTS.COMPLETE, payload)
                    .done((data) => {
                        window.toast.success("Installation complete!");
                        setTimeout(
                            () =>
                            (window.location.href =
                                data?.success && data.redirect
                                    ? data.redirect
                                    : "/login"),
                            1000,
                        );
                    })
                    .fail((xhr) => {
                        console.error("Installation failed:", xhr);
                        const errorMsg =
                            xhr.responseJSON?.message ||
                            "Installation failed. Check console.";
                        window.toast.error(errorMsg);
                    });
            } catch (error) {
                console.error("Complete error:", error);
                window.toast.error("An error occurred. Please try again.");
            }
        }
    }

    const initializeWizard = () => {
        console.log("Document ready, checking install page...");
        if (!utils.isInstallPage()) {
            console.log("Not on install page");
            return;
        }
        if (!utils.isDOMReady()) {
            console.error("DOM not available");
            return;
        }
        console.log("Initializing install wizard...");
        const wizard = new InstallWizard();
        window.installWizard = wizard;
        setTimeout(() => wizard.init(), CONFIG.DELAYS.INIT);
    };

    // Initialize wizard when document is ready
    if (utils.isInstallPage()) {
        if (document.readyState === "loading")
            $(document).ready(initializeWizard);
        else if (
            document.readyState === "interactive" ||
            document.readyState === "complete"
        ) {
            console.log("Document already ready");
            if (utils.isDOMReady()) {
                const wizard = new InstallWizard();
                window.installWizard = wizard;
                setTimeout(() => wizard.init(), 10);
            }
        }
    }
});
