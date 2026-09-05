import { computed, ref } from 'vue'

export function usePageState(initial = 'ready') {
  const state = ref(initial)
  const error = ref(null)

  const loading = computed(() => state.value === 'loading')
  const denied = computed(() => state.value === 'denied')
  const failed = computed(() => state.value === 'error')

  function setError(value) {
    error.value = value instanceof Error ? value.message : String(value || '')
    state.value = 'error'
  }

  return { state, error, loading, denied, failed, setError }
}
