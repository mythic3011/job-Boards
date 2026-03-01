import React, { useState, useEffect } from "react";
import { createRoot } from "react-dom/client";
import ErrorLayout from "../ErrorLayout.jsx";
import "../../src/index.css";

function Page429() {
    const [seconds, setSeconds] = useState(60);

    useEffect(() => {
        if (seconds <= 0) return;
        const t = setTimeout(() => setSeconds((s) => s - 1), 1000);
        return () => clearTimeout(t);
    }, [seconds]);

    return (
        <ErrorLayout
            code="429"
            icon="⏱️"
            title="Too Many Requests"
            subtitle="You've sent too many requests in a short period. Please wait before trying again."
            accent="text-yellow-500"
        >
            <div
                className="inline-flex items-center gap-2 px-4 py-2 rounded-full
                      bg-yellow-950/40 border border-yellow-800/40 text-yellow-400 text-sm"
            >
                {seconds > 0 ? (
                    <>
                        <span className="w-2 h-2 rounded-full bg-yellow-400 animate-pulse" />
                        Retry in {seconds}s
                    </>
                ) : (
                    <>
                        <span className="w-2 h-2 rounded-full bg-green-400" />
                        <a
                            href={window.location.href}
                            className="underline underline-offset-2"
                        >
                            Try again
                        </a>
                    </>
                )}
            </div>
        </ErrorLayout>
    );
}

createRoot(document.getElementById("root")).render(<Page429 />);
