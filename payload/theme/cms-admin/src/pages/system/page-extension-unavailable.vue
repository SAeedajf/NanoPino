<template>
  <LPage icon="plug-zap">
    <LEmptyPanel
      icon="triangle-alert"
      :title="t('extension_unavailable.title')"
      :message="t('extension_unavailable.message')"
    />
  </LPage>
</template>

<script setup>
import { LEmptyPanel, LPage } from '@pinooxhq/luma/ui'
import { t } from '../../i18n/index.js'
</script>
