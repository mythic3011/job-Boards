import { useEffect, useRef, useState } from "react";
import ReactDOM from "react-dom/client";
import "./index.css";

const REDIRECT_DEFAULT = "/monitoring/grafana/";

const SERVICES = [
    { key: "nginx", label: "Nginx" },
    { key: "auth-service", label: "Auth Service" },
    { key: "crowdsec", label: "CrowdSec" },
    { key: "crowdsec-key-init", label: "Key Init" },
    { key: "prometheus", label: "Prometheus" },
    { key: "loki", label: "Loki" },
    { key: "promtail", label: "Promtail" },
    { key: "grafana", label: "Grafana" },
    { key: "laravel.test", label: "Laravel" },
    { key: "postgres", label: "Postgres" },
    { key: "redis", label: "Redis" },
    { key: "sail", label: "Sail" },
];

const ACCESS_SURFACES = [
    "Grafana dashboards and alert investigation.",
    "Prometheus and Loki telemetry checks.",
    "Monitoring-only operator scope (no app account management).",
];

const SECURITY_NOTES = [
    "Monitoring access is audited separately from the main application sign-in flow.",
    "Use these credentials only on devices and networks you control.",
    "Keep application admin login and monitoring operator login as separate operational surfaces.",
];

function ShieldIcon() {
    return (
        <svg
            className="h-8 w-8"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={1.5}
            aria-hidden="true"
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"
            />
        </svg>
    );
}

function SpinnerIcon() {
    return (
        <svg
            className="h-4 w-4 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <circle
                className="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                strokeWidth="4"
            />
            <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v8H4z"
            />
        </svg>
    );
}

function InputField({ id, label, type, value, onChange, autoFocus, disabled }) {
    return (
        <div className="space-y-1.5">
            <label
                htmlFor={id}
                className="theme-text-strong block text-sm font-medium"
            >
                {label}
            </label>
            <input
                id={id}
                type={type}
                value={value}
                onChange={onChange}
                autoFocus={autoFocus}
                disabled={disabled}
                autoComplete={
                    type === "password" ? "current-password" : "username"
                }
                className="theme-input block w-full rounded-xl border px-3 py-2.5 text-sm shadow-sm transition-shadow sm:leading-6"
            />
        </div>
    );
}

function ServiceGrid() {
    return (
        <div className="grid grid-cols-6 gap-2 sm:grid-cols-6">
            {SERVICES.map((service) => (
                <div
                    key={service.key}
                    className="theme-icon-tile flex h-10 w-10 items-center justify-center rounded-xl"
                >
                    <img
                        src={`/monitoring/icons/services/${service.key}.svg`}
                        alt={service.label}
                        title={service.label}
                        className="h-6 w-6"
                        loading="lazy"
                    />
                </div>
            ))}
        </div>
    );
}

function App() {
    const [username, setUsername] = useState("");
    const [password, setPassword] = useState("");
    const [error, setError] = useState("");
    const [loading, setLoading] = useState(false);
    const [success, setSuccess] = useState(false);
    const redirectRef = useRef(REDIRECT_DEFAULT);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const redirect = params.get("redirect");

        if (redirect && redirect.startsWith("/monitoring/")) {
            redirectRef.current = redirect;
        }
    }, []);

    const handleLogin = async (event) => {
        event.preventDefault();

        if (!username.trim() || !password) {
            setError("Please enter your username and password.");
            return;
        }

        setLoading(true);
        setError("");

        try {
            const response = await fetch("/monitoring/auth/verify", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    username: username.trim(),
                    password,
                }),
            });

            if (response.ok) {
                setSuccess(true);
                window.setTimeout(() => {
                    window.location.href = redirectRef.current;
                }, 300);

                return;
            }

            if (response.status === 429) {
                setError("Too many attempts. Please wait before trying again.");
            } else {
                setError("Invalid credentials. Access denied.");
                setPassword("");
            }
        } catch {
            setError("Connection error. Please try again.");
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="theme-page-shell min-h-screen">
            <div className="theme-auth-shell">
                <div className="w-full max-w-5xl space-y-8 px-4">
                    <div className="mx-auto max-w-lg text-center" data-auth-panel-copy>
                        <div className="theme-auth-emblem mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl shadow-sm">
                            <ShieldIcon />
                        </div>

                        <h1 className="theme-text-strong text-3xl font-bold tracking-tight">
                            Sign in to your account
                        </h1>

                        <div className="theme-text-muted mt-2 text-sm leading-6">
                            Use your monitoring operator credentials to continue
                            with the protected observability workspace.
                        </div>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)] lg:items-start">
                        <div className="theme-panel rounded-2xl border p-8 shadow-sm">
                            <form className="space-y-6" onSubmit={handleLogin}>
                                <InputField
                                    id="username"
                                    label="Username"
                                    type="text"
                                    value={username}
                                    onChange={(event) =>
                                        setUsername(event.target.value)
                                    }
                                    autoFocus
                                    disabled={loading || success}
                                />

                                <InputField
                                    id="password"
                                    label="Password"
                                    type="password"
                                    value={password}
                                    onChange={(event) =>
                                        setPassword(event.target.value)
                                    }
                                    disabled={loading || success}
                                />

                                {error ? (
                                    <div
                                        className="theme-alert theme-alert-error rounded-2xl border px-4 py-3 text-sm"
                                        role="alert"
                                        aria-live="polite"
                                    >
                                        {error}
                                    </div>
                                ) : null}

                                {success ? (
                                    <div
                                        className="theme-alert theme-alert-success rounded-2xl border px-4 py-3 text-sm"
                                        role="status"
                                        aria-live="polite"
                                    >
                                        Access granted. Redirecting...
                                    </div>
                                ) : null}

                                <button
                                    type="submit"
                                    disabled={loading || success}
                                    className="theme-button theme-button-primary inline-flex w-full items-center justify-center rounded-lg border px-6 py-3 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {loading ? (
                                        <span className="flex items-center gap-2">
                                            <SpinnerIcon />
                                            Verifying...
                                        </span>
                                    ) : (
                                        "Access Monitoring"
                                    )}
                                </button>
                            </form>
                        </div>

                        <div className="space-y-4">
                            <section className="theme-panel-subtle rounded-2xl border p-6 shadow-sm">
                                <p className="theme-text-muted mb-2 text-xs font-semibold uppercase tracking-[0.16em]">
                                    Access
                                </p>
                                <h2 className="theme-text-strong text-xl font-semibold">
                                    Workspace Access
                                </h2>
                                <ul className="theme-text-muted mt-4 space-y-2 text-sm leading-6">
                                    {ACCESS_SURFACES.map((surface) => (
                                        <li key={surface} className="flex gap-3">
                                            <span className="mt-2 h-2 w-2 rounded-full bg-[var(--app-accent-strong)]" />
                                            <span>{surface}</span>
                                        </li>
                                    ))}
                                </ul>

                                <details className="theme-panel mt-5 rounded-xl border p-4">
                                    <summary className="theme-text-strong cursor-pointer text-sm font-semibold">
                                        Monitored services
                                    </summary>
                                    <div className="mt-3">
                                        <ServiceGrid />
                                    </div>
                                </details>
                            </section>

                            <details className="theme-panel rounded-2xl border p-6 shadow-sm">
                                <summary className="theme-text-strong cursor-pointer text-lg font-semibold">
                                    Security Notes
                                </summary>
                                <p className="theme-text-muted mt-2 text-sm leading-6">
                                    Guidance for safe operator credential usage.
                                </p>
                                <ul className="theme-text-muted mt-4 space-y-3 text-sm leading-6">
                                    {SECURITY_NOTES.map((note) => (
                                        <li key={note} className="flex gap-3">
                                            <span className="mt-2 h-2 w-2 rounded-full bg-[var(--app-accent-strong)]" />
                                            <span>{note}</span>
                                        </li>
                                    ))}
                                </ul>
                            </details>
                        </div>
                    </div>

                    <div className="theme-text-muted text-center text-sm">
                        Monitoring access uses a separate operator credential
                        set from the main PHP application login.
                    </div>
                </div>
            </div>
        </div>
    );
}

const rootElement = document.getElementById("root");

if (rootElement) {
    const root = ReactDOM.createRoot(rootElement);
    root.render(<App />);
} else {
    console.error("root element not found");
}
