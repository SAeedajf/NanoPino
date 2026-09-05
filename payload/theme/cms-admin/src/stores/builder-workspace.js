import { defineStore } from 'pinia'

const VALID_VIEWPORTS = new Set(['desktop', 'tablet', 'mobile'])
const VALID_PANELS = new Set(['blocks', 'layers', 'inspector', 'history'])

export const useBuilderWorkspaceStore = defineStore('cms-builder-workspace', {
  state: () => ({
    document: null,
    selectedBlockId: null,
    viewport: 'desktop',
    mobilePanel: 'blocks',
    dirty: false,
    saving: false,
    lastError: null,
    undoCount: 0,
    redoCount: 0,
  }),

  actions: {
    load(document) {
      this.document = document && typeof document === 'object' ? document : null
      this.selectedBlockId = null
      this.dirty = false
      this.lastError = null
      this.undoCount = 0
      this.redoCount = 0
    },

    select(blockId) {
      this.selectedBlockId = blockId ? String(blockId) : null
    },

    setViewport(viewport) {
      if (!VALID_VIEWPORTS.has(viewport)) return
      this.viewport = viewport
    },

    openPanel(panel) {
      if (!VALID_PANELS.has(panel)) return
      this.mobilePanel = panel
    },

    markDirty(value = true) {
      this.dirty = !!value
    },

    setHistory({ undo = 0, redo = 0 } = {}) {
      this.undoCount = Math.max(0, Number(undo) || 0)
      this.redoCount = Math.max(0, Number(redo) || 0)
    },
  },
})
