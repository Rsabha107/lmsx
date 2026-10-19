<template>
  <div v-if="changes.length" style="border: 1px solid var(--border); border-radius: 6px; overflow: hidden;">
    <div style="background: #DBEAFE; padding: 8px 12px; font-size: 11px; font-weight: 700; color: #1E40AF; text-transform: uppercase; letter-spacing: 0.5px;">
      Flight Changes ({{ changes.length }})
    </div>
    <div style="max-height: 260px; overflow-y: auto;">
      <div v-for="(change, i) in changes" :key="`fc${i}`" style="padding: 8px 12px; border-bottom: 1px solid var(--border); font-size: 12px;">
        <div style="display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: baseline;">
          <span style="font-weight: 700; color: var(--ink);">{{ change.code }}</span>
          <span style="color: var(--ink3); text-transform: capitalize;">{{ change.direction }}</span>
          <span v-if="change.flight_before !== change.flight_after" style="color: var(--ink);">
            {{ change.flight_before || '—' }} → <strong>{{ change.flight_after }}</strong>
          </span>
          <span v-else-if="change.flight_after" style="color: var(--ink3);">{{ change.flight_after }}</span>
          <span v-if="change.time_before !== change.time_after" style="color: var(--ink);">
            {{ change.time_before || '—' }} → <strong>{{ change.time_after }}</strong>
          </span>
          <span v-if="change.pax_before !== change.pax_after" style="color: var(--ink);">
            pax {{ change.pax_before ?? '—' }} → <strong>{{ change.pax_after }}</strong>
          </span>
        </div>
        <div v-if="!change.movements.length" style="margin-top: 3px; color: var(--ink3);">No planned movements affected.</div>
        <div
          v-for="m in change.movements"
          :key="m.id"
          :style="{ marginTop: '4px', padding: '5px 8px', borderRadius: '4px', background: m.status === 'needs_review' ? '#FEF3C7' : 'var(--panel)' }"
        >
          <div style="display: flex; justify-content: space-between; gap: 10px;">
            <span style="color: var(--ink);">
              <strong>{{ m.code }}</strong>
              <span style="color: var(--ink3); text-transform: capitalize;"> · {{ m.kind }}</span>
              <span v-if="m.plan" style="color: var(--ink3);"> · {{ m.plan }}</span>
            </span>
            <span v-if="m.status === 'needs_review'" style="font-weight: 700; color: #92400E; white-space: nowrap;">Needs review</span>
            <span v-else-if="m.from !== m.to" style="color: var(--ink); white-space: nowrap;">{{ m.from || '—' }} → <strong>{{ m.to }}</strong></span>
            <span v-else style="color: #166534; white-space: nowrap;">Updated</span>
          </div>
          <div v-for="(note, n) in m.notes" :key="n" :style="{ fontSize: '11px', color: m.status === 'needs_review' ? '#92400E' : 'var(--ink3)' }">{{ note }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// What a flight change did to the movements planned on it (FlightMovementSync::sync results).
defineProps({ changes: { type: Array, default: () => [] } });
</script>
