import { useState, useEffect, useRef } from "react";
import "./index.css";

const REDIRECT_DEFAULT = "/monitoring/grafana/";

function ShieldIcon() {
    return (
        <svg
            className="w-8 h-8 text-brand-purple"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={1.5}
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6
           3.99 15.954 3.99 12V6a12 12 0 0116.026-.2.75.75 0 01.25.559
           v5.641c0 3.968-2.186 7.506-5.42 9.48a.75.75 0 01-.764 0
           C10.186 19.506 8 15.968 8 12V6.359a.75.75 0 01.25-.559
           A11.959 11.959 0 0112 5.714z"
            />
        </svg>
    );
}

function InputField({ id, label, type, value, onChange, autoFocus, disabled }) {
    return (
        <div className="space-y-1.5">
            <label
                htmlFor={id}
                className="block text-xs font-medium uppercase tracking-widest text-brand-muted"
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
                className="
          w-full px-4 py-3 rounded-lg text-sm text-white
          bg-brand-bg border border-brand-border
          focus:outline-none focus:ring-2 focus:ring-brand-purple focus:border-transparent
          disabled:opacity-50 disabled:cursor-not-allowed
          transition-colors placeholder-brand-muted
        "
            />
        </div>
    );
}

export default function App() {
    const [username, setUsername] = useState("");
    const [password, setPassword] = useState("");
    const [error, setError] = useState("");
    const [loading, setLoading] = useState(false);
    const [success, setSuccess] = useState(false);
    const redirectRef = useRef(REDIRECT_DEFAULT);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const r = params.get("redirect");
        if (r && r.startsWith("/monitoring/")) {
            redirectRef.current = r;
        }
    }, []);

    const handleLogin = async () => {
        if (!username.trim() || !password) {
            setError("Please enter your username and password.");
            return;
        }
        setLoading(true);
        setError("");
        try {
            const res = await fetch("/monitoring/auth/verify", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ username: username.trim(), password }),
            });

            if (res.ok) {
                setSuccess(true);
                setTimeout(() => {
                    window.location.href = redirectRef.current;
                }, 300);
            } else if (res.status === 429) {
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

    const handleKeyDown = (e) => {
        if (e.key === "Enter" && !loading) handleLogin();
    };

    return (
        <div className="min-h-screen bg-brand-bg flex items-center justify-center p-4">
            <div className="w-full max-w-sm">
                {/* Card */}
                <div className="bg-brand-surface border border-brand-border rounded-2xl p-8 shadow-2xl">
                    {/* Header */}
                    <div className="flex flex-col items-center mb-8 space-y-3">
                        <div className="p-3 rounded-full bg-brand-bg border border-brand-border">
                            <ShieldIcon />
                        </div>
                        <div className="text-center">
                            <h1 className="text-lg font-semibold text-white tracking-tight">
                                Monitoring Access
                            </h1>
                            <p className="text-xs text-brand-muted mt-1">
                                Restricted · All attempts logged
                            </p>
                        </div>
                    </div>

                    {/* Error */}
                    {error && (
                        <div className="mb-5 px-4 py-3 rounded-lg bg-red-950/50 border border-red-800/50 text-red-400 text-sm">
                            {error}
                        </div>
                    )}

                    {/* Success */}
                    {success && (
                        <div className="mb-5 px-4 py-3 rounded-lg bg-green-950/50 border border-green-800/50 text-green-400 text-sm">
                            Access granted. Redirecting…
                        </div>
                    )}

                    {/* Form */}
                    <div className="space-y-4" onKeyDown={handleKeyDown}>
                        <InputField
                            id="username"
                            label="Username"
                            type="text"
                            value={username}
                            onChange={(e) => setUsername(e.target.value)}
                            autoFocus
                            disabled={loading || success}
                        />
                        <InputField
                            id="password"
                            label="Password"
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            disabled={loading || success}
                        />
                    </div>

                    {/* Submit */}
                    <button
                        onClick={handleLogin}
                        disabled={loading || success}
                        className="
              mt-6 w-full py-3 rounded-lg text-sm font-semibold
              bg-brand-purple text-brand-bg
              hover:opacity-90 active:scale-[0.98]
              disabled:opacity-50 disabled:cursor-not-allowed
              transition-all duration-150
            "
                    >
                        {loading ? (
                            <span className="flex items-center justify-center gap-2">
                                <svg
                                    className="w-4 h-4 animate-spin"
                                    viewBox="0 0 24 24"
                                    fill="none"
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
                                Verifying…
                            </span>
                        ) : (
                            "Access Monitoring"
                        )}
                    </button>
                </div>

                {/* Footer */}
                <p className="text-center text-xs text-brand-border mt-6">
                    JobBoard Infrastructure
                </p>
            </div>
        </div>
    );
}

import ReactDOM from "react-dom/client";
const rootElement = document.getElementById("root");
if (rootElement) {
    const root = ReactDOM.createRoot(rootElement);
    root.render(<App />);
    console.log("auth frontend bootstrapped");
} else {
    console.error("root element not found");
}
