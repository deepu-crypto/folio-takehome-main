ALTER TABLE documents ADD COLUMN public_id TEXT;

CREATE UNIQUE INDEX idx_documents_public_id
ON documents(public_id);