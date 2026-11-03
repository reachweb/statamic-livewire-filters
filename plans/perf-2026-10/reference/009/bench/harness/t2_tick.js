// T2: tick + untick the "cabrio" checkbox; show whether the model commit and the view's $dispatchTo pool into one POST,
// in which order the two component messages travel, and what the collection ended up applying.
const { setLabel, settle, summarize } = await import('./lwcap.mjs?v=' + Date.now())
const dump = () => state.page.evaluate(() => {
  const col = window.Livewire.all().find(c => c.name === 'livewire-collection')
  const cb = window.Livewire.all().find(c => c.name === 'lf-checkbox-filter' && c.$wire.field === 'special_categories')
  const count = document.querySelector('[wire\\:id] span') // first span inside a component is lf-count's when counts render
  return { collectionParams: col.$wire.params, selected: cb.$wire.selected, entriesShown: document.querySelectorAll('[wire\\:id] img').length, url: location.href, countText: (window.Livewire.all().find(c => c.name === 'lf-count') || {}).el?.innerText?.trim() }
})
console.log('BEFORE tick:', JSON.stringify(await dump()))
setLabel(state, 't2-tick')
await state.page.locator('input#special_categories-cabrio').click()
await settle(state.page, state, 2000, 20000)
console.log(summarize(state, 't2-tick'))
console.log('AFTER tick:', JSON.stringify(await dump()))
setLabel(state, 't2-untick')
await state.page.locator('input#special_categories-cabrio').click()
await settle(state.page, state, 2000, 20000)
console.log(summarize(state, 't2-untick'))
console.log('AFTER untick:', JSON.stringify(await dump()))
console.log('Logs:', await getLatestLogs({ page: state.page, sinceLastCall: true, search: /error|uncaught/i }))
