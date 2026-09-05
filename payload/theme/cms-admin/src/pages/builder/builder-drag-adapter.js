export function createBuilderDropIntent({
  sourceId,
  targetParentId = null,
  index = 0,
  slot = null,
} = {}) {
  const source = String(sourceId || '').trim()
  if (!source) throw new Error('Drag source is required.')

  const parsedIndex = Number(index)
  if (!Number.isInteger(parsedIndex) || parsedIndex < 0) {
    throw new Error('Drop index must be a non-negative integer.')
  }

  return Object.freeze({
    sourceId: source,
    targetParentId: targetParentId == null ? null : String(targetParentId),
    index: parsedIndex,
    slot: slot == null ? null : String(slot),
  })
}

export function isKeyboardReorderKey(event) {
  return !!event?.altKey && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)
}
