import test from 'node:test'
import assert from 'node:assert/strict'
import { keepSavedName } from './name-rules.mjs'

test('a profile update is not an address-book save', () => {
  assert.equal(keepSavedName('Pa. Nasrah Umwali', 'Nasrah', false), 'Pa. Nasrah Umwali')
})

test('a shorter fragment does not replace the saved name', () => {
  assert.equal(keepSavedName('Pa. Nasrah Umwali', 'Nasrah', true), 'Pa. Nasrah Umwali')
})

test('a longer saved name that contains the short one is kept', () => {
  assert.equal(keepSavedName('Nasrah', 'Pa. Nasrah Umwali', true), 'Pa. Nasrah Umwali')
})

test('a different chat name does not replace another saved name unless it is the address book', () => {
  assert.equal(keepSavedName('David Office', 'David Church', false), 'David Office')
})

test('the address book can rename the same contact', () => {
  assert.equal(keepSavedName('David Office', 'David Church', true), 'David Church')
})
