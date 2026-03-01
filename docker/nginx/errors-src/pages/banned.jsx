import React from "react";
import { createRoot } from "react-dom/client";
import ErrorLayout from "../ErrorLayout.jsx";
import "../../src/index.css";

function PageBanned() {
    return (
        <ErrorLayout
            code="403"
            icon="🚫"
            title="Access Denied"
            subtitle="Your IP address has been blocked due to suspicious activity. If you believe this is an error, contact the administrator."
            accent="text-red-500"
        >
            <div
                className="inline-flex items-center gap-2 px-4 py-2 rounded-full
                      bg-red-950/40 border border-red-800/40 text-red-400 text-sm"
            >
                <span className="w-2 h-2 rounded-full bg-red-400" />
                Blocked by CrowdSec
            </div>
        </ErrorLayout>
    );
}

createRoot(document.getElementById("root")).render(<PageBanned />);
