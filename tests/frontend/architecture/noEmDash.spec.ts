import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'

import ts from 'typescript'
import { describe, expect, it } from 'vitest'

// K9 (pemilik 2026-11-13) — teks yang dilihat user tidak memuat "—". Komentar kode bukan teks UI,
// jadi yang diperiksa hanya isi <template> (tanpa komentar HTML) dan string literal di skrip.
const ROOT = join(__dirname, '../../../resources/js')
const DASH = /—/
// Paket 2 P1 — istilah yang bocor dari bahasa Inggris atau terjemahan lama (glosarium UI).
const FORBIDDEN_TERMS = /\b(Dasbor|Baca-saja|Laravel)\b/
const STRING_TOKENS = new Set([
    ts.SyntaxKind.StringLiteral,
    ts.SyntaxKind.NoSubstitutionTemplateLiteral,
    ts.SyntaxKind.TemplateHead,
    ts.SyntaxKind.TemplateMiddle,
    ts.SyntaxKind.TemplateTail,
])

function files(dir: string): string[] {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name)
        if (statSync(path).isDirectory()) return files(path)

        return /\.(ts|vue)$/.test(name) && !name.endsWith('.d.ts') ? [path] : []
    })
}

function lineOf(source: string, index: number): number {
    return source.slice(0, index).split('\n').length
}

function scriptOffenders(code: string, offset: number, source: string, needle: RegExp): number[] {
    const scanner = ts.createScanner(
        ts.ScriptTarget.Latest,
        false,
        ts.LanguageVariant.Standard,
        code
    )
    const lines: number[] = []
    // `}` menutup ekspresi template hanya bila kurung terdalam dibuka oleh `${`.
    const braces: ('block' | 'template')[] = []
    for (let kind = scanner.scan(); kind !== ts.SyntaxKind.EndOfFileToken; kind = scanner.scan()) {
        if (kind === ts.SyntaxKind.OpenBraceToken) braces.push('block')
        if (kind === ts.SyntaxKind.CloseBraceToken) {
            if (braces.pop() === 'template') kind = scanner.reScanTemplateToken(false)
        }
        if (kind === ts.SyntaxKind.TemplateHead || kind === ts.SyntaxKind.TemplateMiddle) {
            braces.push('template')
        }
        if (STRING_TOKENS.has(kind) && needle.test(scanner.getTokenText())) {
            lines.push(lineOf(source, offset + scanner.getTokenStart()))
        }
    }

    return lines
}

function offenders(path: string, needle: RegExp): string[] {
    const source = readFileSync(path, 'utf8')
    const lines: number[] = []

    if (path.endsWith('.ts')) {
        lines.push(...scriptOffenders(source, 0, source, needle))
    } else {
        for (const match of source.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g)) {
            const code = match[1] ?? ''
            lines.push(
                ...scriptOffenders(
                    code,
                    (match.index ?? 0) + match[0].indexOf(code),
                    source,
                    needle
                )
            )
        }
        const template = /<template>([\s\S]*)<\/template>/.exec(source)
        if (template) {
            const start = (template.index ?? 0) + '<template>'.length
            const body = (template[1] ?? '').replace(/<!--[\s\S]*?-->/g, (c) =>
                c.replace(/[^\n]/g, ' ')
            )
            body.split('\n').forEach((line, i) => {
                if (needle.test(line)) lines.push(lineOf(source, start) + i)
            })
        }
    }

    return lines.map((line) => `${relative(ROOT, path)}:${line}`)
}

describe('K9 tanpa em-dash di UI', () => {
    it('teks template dan string skrip di resources/js tidak memuat "—"', () => {
        expect(files(ROOT).flatMap((path) => offenders(path, DASH))).toEqual([])
    })

    it('teks template dan string skrip tidak memuat istilah terlarang', () => {
        expect(files(ROOT).flatMap((path) => offenders(path, FORBIDDEN_TERMS))).toEqual([])
    })
})
