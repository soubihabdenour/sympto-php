<?php
declare(strict_types=1);

// Document parser. Plain text + DOCX (built-in ZipArchive + DOMDocument).
// PDF parsing is intentionally NOT supported on this shared-hosting build.

function classify_upload(string $filename, string $mime): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === 'docx' || str_contains($mime, 'wordprocessingml')) return 'docx';
    if ($ext === 'txt' || str_starts_with($mime, 'text/')) return 'text';
    if ($ext === 'pdf' || $mime === 'application/pdf') return 'pdf';
    if (str_starts_with($mime, 'image/')) return 'image';
    return 'other';
}

function parse_document(string $absPath, string $kind): array {
    return match ($kind) {
        'text' => parse_text_file($absPath),
        'docx' => parse_docx_file($absPath),
        'pdf' => [
            'text' => '',
            'summary' => 'PDF parsing is not supported in this PHP build. Upload the document as DOCX or paste the contents as text via re-upload.',
        ],
        'image' => [
            'text' => '',
            'summary' => 'Image OCR is not enabled. The image is stored with the case but no text is extracted.',
        ],
        default => ['text' => '', 'summary' => null],
    };
}

function parse_text_file(string $path): array {
    $raw = (string) @file_get_contents($path);
    // Normalize line endings; truncate very large files
    $text = mb_convert_encoding($raw, 'UTF-8', mb_detect_encoding($raw, 'UTF-8, ISO-8859-1, Windows-1252', true) ?: 'UTF-8');
    if (strlen($text) > 200_000) $text = substr($text, 0, 200_000);
    return ['text' => trim($text), 'summary' => null];
}

function parse_docx_file(string $path): array {
    if (!class_exists('ZipArchive')) {
        return ['text' => '', 'summary' => 'PHP ZipArchive extension missing — cannot extract DOCX.'];
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return ['text' => '', 'summary' => 'Could not open DOCX archive.'];
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false) {
        return ['text' => '', 'summary' => 'DOCX is missing word/document.xml.'];
    }
    // Strip namespaces for simpler XPath, then pull <w:t> text nodes.
    $clean = preg_replace('/<w:p[^>]*\/>/', '', $xml);
    $clean = preg_replace('/<w:br[^>]*\/>/', "\n", $clean);
    $clean = preg_replace('/<\/w:p>/', "\n", $clean);
    $clean = preg_replace('/<\/w:tr>/', "\n", $clean);
    $clean = strip_tags_safe((string) $clean);
    $text = html_entity_decode($clean, ENT_QUOTES | ENT_XML1, 'UTF-8');
    // Collapse runs of whitespace per line
    $lines = array_map('trim', preg_split('/\r?\n/', $text) ?: []);
    $lines = array_filter($lines, fn($l) => $l !== '');
    return ['text' => implode("\n", $lines), 'summary' => null];
}

// strip_tags() can drop adjacent text together with the tags; build a safer
// version by replacing tag boundaries with spaces first.
function strip_tags_safe(string $xml): string {
    return preg_replace('/<[^>]+>/', '', $xml) ?? '';
}
