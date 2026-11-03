const { attach, settle, summarize } = await import('./lwcap.mjs?v=' + Date.now())
if (!state.page || state.page.isClosed()) state.page = context.pages().findLast((p) => p.url() === 'about:blank') ?? (await context.newPage())
if (state.onReq) { state.page.off('request', state.onReq); state.page.off('requestfinished', state.onFin) }
await attach({ page: state.page, state })
await state.page.goto(state.url || 'http://127.0.0.1:8102/advanced', { waitUntil: 'domcontentloaded' })
await settle(state.page, state, 2000, 20000)
console.log('URL:', state.page.url())
console.log(summarize(state, 'load'))
