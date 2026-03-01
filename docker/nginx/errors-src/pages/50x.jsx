import React from "react";
import { createRoot } from "react-dom/client";
import ErrorLayout from "../ErrorLayout.jsx";
import "../../src/index.css";

function Page50x() {
    const ts = new Date().toUTCString();

    return (
        <ErrorLayout
            code="5xx"
            icon="🔧"
            title="Server Error"
            subtitle="Something went wrong on our end. The team has been notified. Please try again shortly."
            accent="text-red-500"
        >
            <div
                className="px-4 py-3 rounded-lg bg-brand-surface border border-brand-border
                      text-xs text-brand-muted text-left space-y-1 font-mono"
            >
                <div className="flex justify-between">
                    <span>Time</span>
                    <span className="text-white">{ts}</span>
                </div>
                <div className="flex justify-between">
                    <span>Status</span>
                    <span className="text-red-400">Unavailable</span>
                </div>
            </div>

            <button
                onClick={() => window.location.reload()}
                className="mt-4 px-5 py-2 rounded-lg text-sm font-medium
                   bg-brand-surface border border-brand-border text-brand-purple
                   hover:border-brand-purple transition-colors"
            >
                Retry
            </button>
        </ErrorLayout>
    );
}

createRoot(document.getElementById("root")).render(<Page50x />);
