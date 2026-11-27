import assert from 'assert'
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { bundledBaileysVersion, chooseClientVersion } from './client-version.mjs'

const root = path.dirname(fileURLToPath(import.meta.url))
const bundled = bundledBaileysVersion(root)
assert.deepStrictEqual(bundled.slice(0, 2), [2, 3000])
assert.strictEqual(bundled.length, 3)

const live = chooseClientVersion({ isLatest: true, version: [2, 3000, 1056426862] }, bundled)
assert.deepStrictEqual(live, [2, 3000, 1056426862])

const fallback = chooseClientVersion({ isLatest: false, version: [2, 3000, 1] }, bundled)
assert.deepStrictEqual(fallback, bundled)

const source = fs.readFileSync(path.join(root, 'server.mjs'), 'utf8')
assert.ok(source.includes('fetchLatestBaileysVersion'))
assert.strictEqual(source.includes('fetchLatestWaWebVersion'), false)

console.log('client-version ok')
