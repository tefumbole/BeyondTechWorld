import assert from 'assert'
import fs from 'fs'
import path from 'path'
import vm from 'vm'
import { fileURLToPath } from 'url'

const source = fs.readFileSync(path.join(path.dirname(fileURLToPath(import.meta.url)), '../../public/js/mai-install.js'), 'utf8')

function boot(ua) {
  const nodes = []
  function element(tag) {
    const node = {
      tagName: tag,
      id: '',
      hidden: false,
      disabled: false,
      textContent: '',
      style: {},
      children: [],
      listeners: {},
      setAttribute(name, value) { node.attrs[name] = value },
      getAttribute(name) { return node.attrs[name] || null },
      appendChild(child) { node.children.push(child); return child },
      addEventListener(type, fn) { (node.listeners[type] = node.listeners[type] || []).push(fn) },
      attrs: {},
    }
    nodes.push(node)
    return node
  }
  const button = element('button')
  button.attrs['data-mai-install'] = ''
  button.textContent = 'Install on Android'
  button.closest = function (selector) { return selector === '[data-mai-install]' ? button : null }
  const documentListeners = {}
  const windowListeners = {}
  const document = {
    addEventListener(type, fn) { (documentListeners[type] = documentListeners[type] || []).push(fn) },
    querySelectorAll() { return [button] },
    getElementById(id) { return nodes.find((node) => node.id === id) || null },
    createElement: element,
    head: element('head'),
    body: element('body'),
    documentElement: element('html'),
  }
  const location = { href: 'https://mai.beyondtechworld.com/whatsapp-mai/open' }
  const window = {
    navigator: {
      userAgent: ua,
      serviceWorker: { register() { return Promise.resolve() } },
    },
    matchMedia() { return { matches: false } },
    addEventListener(type, fn) { (windowListeners[type] = windowListeners[type] || []).push(fn) },
    location,
    document,
    __maiInstall: { event: null, installed: false },
  }
  window.window = window
  vm.runInNewContext(source, { window, document, navigator: window.navigator, location }, { filename: 'mai-install.js' })
  return { window, document, documentListeners, windowListeners, button, location }
}

function click(page) {
  page.documentListeners.click.forEach((fn) => fn({
    target: page.button,
    preventDefault() {},
  }))
}

function sheetText(page) {
  const sheet = page.document.getElementById('mai-install-sheet')
  if (!sheet) return ''
  return sheet.children.map((child) => child.textContent).join(' ')
}

const chrome = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36'
const prompted = boot(chrome)
let promptCalls = 0
const promptEvent = {
  preventDefault() {},
  prompt() { promptCalls += 1; return Promise.resolve() },
  userChoice: Promise.resolve({ outcome: 'dismissed' }),
}
prompted.windowListeners.beforeinstallprompt.forEach((fn) => fn(promptEvent))
click(prompted)
assert.strictEqual(promptCalls, 1)
assert.notStrictEqual(prompted.button.textContent, 'Installed')
await promptEvent.userChoice
await new Promise((resolve) => setTimeout(resolve, 0))
assert.ok(sheetText(prompted).includes('Install was dismissed'))
assert.strictEqual(prompted.button.textContent, 'Install on Android')

click(prompted)
assert.strictEqual(promptCalls, 1)
assert.ok(sheetText(prompted).includes('Install app'))

const again = boot(chrome)
const second = {
  preventDefault() {},
  prompt() { promptCalls += 1; return Promise.resolve() },
  userChoice: Promise.resolve({ outcome: 'accepted' }),
}
again.windowListeners.beforeinstallprompt.forEach((fn) => fn(second))
click(again)
click(again)
assert.strictEqual(promptCalls, 2)
assert.notStrictEqual(again.button.textContent, 'Installed')
again.windowListeners.appinstalled.forEach((fn) => fn())
assert.strictEqual(again.button.textContent, 'Installed')

const whatsApp = boot('Mozilla/5.0 (Linux; Android 14; wv) AppleWebKit/537.36 WhatsApp/2.24')
click(whatsApp)
const help = sheetText(whatsApp)
assert.ok(help.includes('Open in Chrome'))
assert.ok(help.includes('https://mai.beyondtechworld.com/whatsapp-mai/open'))
assert.notStrictEqual(whatsApp.button.textContent, 'Installed')
assert.strictEqual(whatsApp.location.href, 'https://mai.beyondtechworld.com/whatsapp-mai/open')

console.log('mai-install ok')
