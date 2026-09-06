# Builder Architecture

Builder stores versioned documents and revisions and exposes open/create/read/save/autosave/publish/preview/restore operations through the runtime API.

The admin editor includes block library, layers, inspector, responsive styles, undo/redo, pointer/touch movement, autosave and revision history.

State-integrity rules:
- save/publish acknowledge only the submitted snapshot;
- newer local edits remain dirty;
- pointer cancellation never commits a drop;
- retries must not duplicate successful mutations.

Reusable Global Blocks and active-theme Patterns use the same canvas/document model.
