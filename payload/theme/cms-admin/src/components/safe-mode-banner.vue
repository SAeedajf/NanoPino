<template>
  <LPanel v-if="safeMode?.enabled" class="cms-safe-mode" surface="muted" role="status" aria-live="polite">
    <div class="cms-safe-mode__content">
      <div>
        <div class="cms-safe-mode__title">
          <LIcon name="shield-alert" :size="20" />
          {{ t('safe_mode.title') }}
        </div>
        <p class="cms-safe-mode__text">
          {{ safeMode.reason || t('safe_mode.default_reason') }}
        </p>
      </div>
      <LBadge severity="warn">{{ safeMode.quarantined?.length || 0 }} {{ t('safe_mode.quarantined') }}</LBadge>
    </div>
  </LPanel>
</template>

<script setup>
import { LBadge, LIcon, LPanel } from '@pinooxhq/luma/ui'
import { t } from '../i18n/index.js'
defineProps({ safeMode: { type: Object, default: () => ({}) } })
</script>
