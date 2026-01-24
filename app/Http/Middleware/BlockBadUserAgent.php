<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BlockBadUserAgent
{
    /**
     * List of bad user agent patterns to block.
     */
    protected array $BadPatterns = [
        'sqlmap',
        'nikto',
        'nmap',
        'masscan',
        'zap',
        'burp',
        'w3af',
        'acunetix',
        'nessus',
        'openvas',
        'metasploit',
        'havij',
        'pangolin',
        'sqlsus',
        'sqlninja',
        'wpscan',
        'joomscan',
        'drupalscan',
        'cmsmap',
        'whatweb',
        'dirb',
        'dirbuster',
        'gobuster',
        'wfuzz',
        'ffuf',
        'dirsearch',
        'feroxbuster',
        'ffuf',
        'hydra',
        'medusa',
        'patator',
        'brutespray',
        'ncrack',
        'john',
        'hashcat',
        'aircrack',
        'reaver',
        'wifite',
        'kismet',
        'ettercap',
        'bettercap',
        'mitmproxy',
        'charles',
        'fiddler',
        'postman',
        'insomnia',
        'curl',
        'wget',
        'python-requests',
        'python-urllib',
        'go-http-client',
        'java/',
        'scrapy',
        'scraper',
        'crawler',
        'spider',
        'bot',
        'hack',
        'exploit',
        'inject',
        'xss',
        'sqli',
        'lfi',
        'rfi',
        'shell',
        'cmd',
        'eval',
        'base64',
        'phpinfo',
        '<?php',
        '<script',
        'javascript:',
        'onerror=',
        'onload=',
        'onclick=',
        'union select',
        'drop table',
        'delete from',
        'insert into',
        'update set',
        'exec(',
        'system(',
        'passthru(',
        'shell_exec(',
        'eval(',
        'assert(',
        'file_get_contents',
        'file_put_contents',
        'fopen',
        'fwrite',
        'readfile',
        'include',
        'require',
        '$_GET',
        '$_POST',
        '$_REQUEST',
        '$_COOKIE',
        '$_SERVER',
        '$_FILES',
        '$_ENV',
        '$_SESSION',
        'GLOBALS',
        'php://',
        'data://',
        'expect://',
        'file://',
        'http://',
        'https://',
        'ftp://',
        'ldap://',
        'gopher://',
        'dict://',
        '..',
        '../',
        '....//',
        '....\\\\',
        '%2e%2e',
        '%2e%2e%2f',
        '%2e%2e%5c',
        '%00',
        '\x00',
        '\0',
        '\r',
        '\n',
        '\t',
        'chr(',
        'ord(',
        'hex2bin',
        'bin2hex',
        'str_rot13',
        'base64_decode',
        'urldecode',
        'rawurldecode',
        'htmlspecialchars_decode',
        'html_entity_decode',
        'stripslashes',
        'addslashes',
        'quotemeta',
        'escapeshellarg',
        'escapeshellcmd',
        'escapeshellarg',
        'escapeshellcmd',
        'preg_replace',
        'preg_match',
        'preg_match_all',
        'preg_split',
        'preg_filter',
        'preg_grep',
        'preg_quote',
        'preg_replace_callback',
        'preg_replace_callback_array',
        'preg_last_error',
        'mb_ereg_replace',
        'mb_eregi_replace',
        'mb_ereg_replace_callback',
        'mb_ereg_replace_callback_array',
        'mb_ereg',
        'mb_eregi',
        'mb_ereg_match',
        'mb_ereg_search',
        'mb_ereg_search_pos',
        'mb_ereg_search_regs',
        'mb_ereg_search_init',
        'mb_ereg_search_getregs',
        'mb_ereg_search_setpos',
        'mb_ereg_search_getpos',
        'mb_ereg_search_getpos',
        'mb_split',
        'mb_ereg_search',
        'mb_ereg_search_pos',
        'mb_ereg_search_regs',
        'mb_ereg_search_init',
        'mb_ereg_search_getregs',
        'mb_ereg_search_setpos',
        'mb_ereg_search_getpos',
        'mb_split',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = $request->userAgent() ?? '';

        // Check if user agent is bad
        foreach ($this->BadPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                Log::warning('Blocked suspicious user agent', [
                    'user_agent' => $userAgent,
                    'ip' => $request->ip(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'pattern_matched' => $pattern,
                ]);

                // block the request
                abort(403, 'Access denied.');
            }
        }

        // Block empty or very short user agents
        if (strlen($userAgent) < 10) {
            Log::warning('Blocked suspicious user agent (too short)', [
                'user_agent' => $userAgent,
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
