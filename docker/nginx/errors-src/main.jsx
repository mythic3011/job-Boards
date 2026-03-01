import React from "react";
import "../../src/index.css";

export default function ErrorLayout({
    code,
    title,
    subtitle,
    icon,
    accent = "text-brand-purple",
    children,
}) {
    return (
        <div className="min-h-screen bg-brand-bg flex items-center justify-center p-4 font-sans">
            <div className="w-full max-w-md text-center space-y-6">
                {/* Code */}
                <div
                    className={`text-8xl font-black tracking-tighter ${accent} opacity-20 select-none`}
                >
                    {code}
                </div>

                {/* Icon + Title */}
                <div className="space-y-3">
                    <div className="text-4xl">{icon}</div>
                    <h1 className="text-xl font-semibold text-white">
                        {title}
                    </h1>
                    <p className="text-sm text-brand-muted max-w-xs mx-auto leading-relaxed">
                        {subtitle}
                    </p>
                </div>

                {/* Extra content */}
                {children && <div className="mt-4">{children}</div>}

                {/* Footer */}
                <p className="text-xs text-brand-border pt-4 border-t border-brand-border">
                    JobBoard Infrastructure
                </p>
            </div>
        </div>
    );
}
