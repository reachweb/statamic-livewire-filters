// Capture helper for Livewire update traffic via Playwright page events.
const isUpdate = (url) => /\/livewire[^/]*\/update/.test(url)
export async function attach({ page, state }) {
  state.log = []
  state.label = 'load'
  state.byReq = new Map()
  state.onReq = (req) => {
    if (!isUpdate(req.url())) return
    const rec = { label: state.label, post: req.postData() || '', postBytes: (req.postData() || '').length, t0: Date.now() }
    state.byReq.set(req, rec)
    state.log.push(rec)
  }
  state.onFin = async (req) => {
    const rec = state.byReq.get(req); if (!rec) return
    try {
      const sizes = await req.sizes(); rec.sizes = sizes
      rec.wireBytes = sizes.responseBodySize; rec.upWire = sizes.requestBodySize
      const t = req.timing(); rec.timing = t
      rec.ttfbMs = Math.round(t.responseStart - t.requestStart)
      rec.totalMs = Math.round(t.responseEnd - t.requestStart)
      const resp = await req.response(); rec.status = resp.status(); rec.body = await resp.text()
    } catch (e) { rec.err = String(e) }
    rec.done = true
  }
  page.on('request', state.onReq)
  page.on('requestfinished', state.onFin)
}
export function setLabel(state, label) { state.label = label }
export async function settle(page, state, quietMs = 1500, maxMs = 15000) {
  const start = Date.now(); let lastCount = state.log.length; let lastChange = Date.now()
  while (Date.now() - start < maxMs) {
    await page.waitForTimeout(100)
    const pending = state.log.some((r) => !r.done)
    if (state.log.length !== lastCount) { lastCount = state.log.length; lastChange = Date.now() }
    if (!pending && Date.now() - lastChange > quietMs) break
  }
}
function short(v) { const s = JSON.stringify(v); return s.length > 70 ? s.slice(0, 67) + '...' : s }
export function summarize(state, label) {
  const rows = state.log.filter((r) => r.label === label)
  const out = [`### action "${label}": ${rows.length} POST livewire/update request(s)`]
  let up = 0, down = 0, raw = 0, serverMs = 0
  rows.forEach((r, i) => {
    up += r.postBytes; down += r.wireBytes || 0; raw += (r.body || '').length; serverMs += r.ttfbMs || 0
    let req; try { req = JSON.parse(r.post) } catch { req = { components: [] } }
    let res; try { res = JSON.parse(r.body || '{}') } catch { res = { components: [] } }
    out.push(`  #${i + 1} status=${r.status} up=${r.postBytes}B down(wire)=${r.wireBytes}B down(raw)=${(r.body || '').length}B ttfb=${r.ttfbMs}ms total=${r.totalMs}ms components=${req.components.length}${r.err ? ' ERR=' + r.err : ''}`)
    req.components.forEach((c, j) => {
      let snap; try { snap = JSON.parse(c.snapshot) } catch { snap = { memo: { name: '?' } } }
      const calls = (c.calls || []).map((k) => `${k.method}(${k.params.map(short).join(', ')})`).join('; ')
      const updates = Object.keys(c.updates || {}).length ? ' updates=' + JSON.stringify(c.updates) : ''
      const rc = (res.components || [])[j] || {}; const eff = rc.effects || {}
      const rendered = eff.html !== undefined
      const disp = (eff.dispatches || []).map((d) => d.name + (d.component ? '→' + d.component : '')).join(',')
      out.push(`     - ${snap.memo.name.padEnd(20)} snap=${c.snapshot.length}B  call: ${calls}${updates}  => rendered=${rendered}${rendered ? ' html=' + eff.html.length + 'B' : ''} respSnap=${(rc.snapshot || '').length}B${disp ? ' dispatches=[' + disp + ']' : ''}`)
    })
  })
  out.push(`  TOTAL: requests=${rows.length} up=${up}B down(wire)=${down}B down(raw)=${raw}B server(ttfb sum)=${serverMs}ms`)
  return out.join('\n')
}
