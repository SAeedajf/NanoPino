# Builder Architecture

Builder stores versioned documents and revisions and exposes open/create/read/save/autosave/publish/preview/restore operations through the runtime API.

The admin editor includes block library, layers, inspector, responsive styles, undo/redo, pointer/touch movement, autosave and revision history.

State-integrity rules:
- save/publish acknowledge only the submitted snapshot;
- newer local edits remain dirty;
- pointer cancellation never commits a drop;
- retries must not duplicate successful mutations.

Reusable Global Blocks and active-theme Patterns use the same canvas/document model.


## Admin target workflow

Builder admin treats selecting a target, opening an existing document, and creating a new override as separate operations.

- **Open existing** performs list/read only and never creates a document.
- **Create new document** uses the explicit Builder create route and fails if the target already exists.
- The target is represented to editors by named content/template/template-part/site selectors; the raw target key is secondary technical metadata.
- Once a document is open, target controls are locked to the record. Closing a dirty document requires explicit confirmation.
- Block insertion is unavailable until a concrete document is open.

This preserves BuilderTarget as the domain contract while preventing ambiguous UI actions from mutating state.
