// /preset: tag-authored taxonomy:car_brand:any="toyota|hyundai|vw" + lf-checkbox-filter(car_brand). Press lf-tags "Clear all".
const { attach, setLabel, settle, summarize } = await import('./lwcap.mjs?v=' + Date.now())
if (!state.page || state.page.isClosed()) state.page = context.pages().findLast((p) => p.url() === 'about:blank') ?? (await context.newPage())
if (state.onReq) { state.page.off('request', state.onReq); state.page.off('requestfinished', state.onFin) }
await attach({ page: state.page, state })
await state.page.goto('http://127.0.0.1:8102/preset', { waitUntil: 'domcontentloaded' })
await settle(state.page, state, 2000, 20000)
console.log(summarize(state, 'load'))
const dump = () => state.page.evaluate(() => {
  const col = window.Livewire.all().find(c => c.name === 'livewire-collection')
  const cb = window.Livewire.all().find(c => c.name === 'lf-checkbox-filter' && c.$wire.field === 'car_brand')
  const tags = window.Livewire.all().find(c => c.name === 'lf-tags')
  return { collectionParams: col.$wire.params, carBrandSelected: cb.$wire.selected, tagsCount: (tags && tags.$wire.tags || []).length, tagsInDom: document.querySelectorAll('button[wire\\:key]').length, entriesShown: document.querySelectorAll('[wire\\:id] img').length, url: location.href }
})
console.log('BEFORE clear-all:', JSON.stringify(await dump()))
setLabel(state, 'preset-tags-clear-all')
await state.page.locator('button[wire\\:click="clearAll()"]').first().click()
await settle(state.page, state, 2000, 20000)
console.log(summarize(state, 'preset-tags-clear-all'))
console.log('AFTER clear-all:', JSON.stringify(await dump()))
