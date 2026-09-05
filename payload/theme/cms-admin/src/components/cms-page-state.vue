<template>
  <div
    class="cms-page-state-region"
    :role="state === 'error' || state === 'denied' ? 'alert' : 'status'"
    :aria-live="state === 'error' || state === 'denied' ? 'assertive' : 'polite'"
    :aria-busy="state === 'loading' ? 'true' : 'false'"
  >
  <LEmptyPanel
    v-if="state === 'loading'"
    loading
    :title="t('state.loading_title')"
    :message="t('state.loading_message')"
  />
  <LEmptyPanel
    v-else-if="state === 'denied'"
    icon="shield-x"
    :title="t('state.denied_title')"
    :message="t('state.denied_message')"
  />
  <LEmptyPanel
    v-else-if="state === 'error'"
    icon="triangle-alert"
    :title="t('state.error_title')"
    :message="message || t('state.error_message')"
    :action-label="t('state.retry')"
    @action="$emit('retry')"
  />
  <LEmptyPanel
    v-else-if="state === 'empty'"
    :icon="emptyIcon"
    :title="emptyTitle || t('state.empty_title')"
    :message="emptyMessage"
  />
  <slot v-else />
  </div>
</template>

<script setup>
import { LEmptyPanel } from '@pinooxhq/luma/ui'
import { t } from '../i18n/index.js'

defineProps({
  state: { type: String, default: 'ready' },
  message: { type: String, default: '' },
  emptyIcon: { type: String, default: 'inbox' },
  emptyTitle: { type: String, default: '' },
  emptyMessage: { type: String, default: '' },
})

defineEmits(['retry'])
</script>
