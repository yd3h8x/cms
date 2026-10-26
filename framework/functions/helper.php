<?php
declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function proper_case(string $name): string
{
    return ucwords(str_replace('_', ' ', $name));
}

function is_local_request(): bool
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($remote, ['127.0.0.1', '::1'], true);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $sent = (string)($_POST['_csrf'] ?? '');
    if (!hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('Invalid or expired request token.');
    }
}

function safe_return_url(string $url): string
{
    return str_starts_with($url, '/') && !str_starts_with($url, '//')
        ? $url
        : '/index.php';
}

function render_comment_attributes(string $comment): string
{
    $map = [
        'ACC'=>'accept','ACCH'=>'accept-charset','ACK'=>'accesskey','ACT'=>'action',
        'ALT'=>'alt','ARIA'=>'aria-*','ACAP'=>'autocapitalize','AC'=>'autocomplete',
        'ACOR'=>'autocorrect','AF'=>'autofocus','CAP'=>'capture','CHK'=>'checked',
        'C'=>'class','COL'=>'cols','CED'=>'contenteditable','DATA'=>'data-*','DIR'=>'dir',
        'DN'=>'dirname','D'=>'disabled','DRAG'=>'draggable','ENC'=>'enctype','EKH'=>'enterkeyhint',
        'FOR'=>'for','FORM'=>'form','FACT'=>'formaction','FENC'=>'formenctype',
        'FMET'=>'formmethod','FNV'=>'formnovalidate','FTGT'=>'formtarget','H'=>'height',
        'HID'=>'hidden','HIGH'=>'high','ID'=>'id','IM'=>'inputmode','L'=>'label','LANG'=>'lang',
        'LIST'=>'list','LOW'=>'low','MAX'=>'max','ML'=>'maxlength','MET'=>'method','MIN'=>'min',
        'MNL'=>'minlength','MULT'=>'multiple','N'=>'name','NVAL'=>'novalidate','ONC'=>'onclick',
        'ONCH'=>'onchange','OPT'=>'optimum','PART'=>'part','PAT'=>'pattern','PH'=>'placeholder',
        'RO'=>'readonly','REL'=>'rel','R'=>'required','ROLE'=>'role','ROWS'=>'rows','SEL'=>'selected',
        'SZ'=>'size','SLOT'=>'slot','SPELL'=>'spellcheck','SRC'=>'src','STEP'=>'step','STY'=>'style',
        'TAB'=>'tabindex','TGT'=>'target','TITLE'=>'title','TRANS'=>'translate','T'=>'type',
        'V'=>'value','W'=>'width','WRAP'=>'wrap',
    ];

    preg_match_all('/([A-Z]+)(?:="([^"]*)")?/', $comment, $matches, PREG_SET_ORDER);
    $attributes = [];

    foreach ($matches as $match) {
        $key = $match[1];
        if (!isset($map[$key])) {
            continue;
        }

        $attribute = $map[$key];
        $value = $match[2] ?? null;

        if (str_ends_with($attribute, '*')) {
            if ($value === null || !preg_match('/^[a-zA-Z0-9_.:-]+$/', $value)) {
                continue;
            }
            $attribute = str_replace('*', $value, $attribute);
        } elseif (!preg_match('/^[a-z][a-z0-9-]*$/', $attribute)) {
            continue;
        }

        if ($value === null) {
            $attributes[] = in_array($attribute, [
                'checked','disabled','autofocus','multiple','readonly','required',
                'hidden','novalidate','formnovalidate','selected',
            ], true) ? $attribute : $attribute . '=""';
        } else {
            $attributes[] = $attribute . '="' . h($value) . '"';
        }
    }

    return implode(' ', $attributes);
}

function redirect(string $url): never
{
    header('Location: ' . safe_return_url($url));
    exit;
}

function error_page(string $message, int $status = 400): never
{
    http_response_code($status);
    echo '<!doctype html><meta charset="utf-8"><title>Error</title><p>'
        . h($message) . '</p><p><a href="/index.php">Home</a></p>';
    exit;
}
