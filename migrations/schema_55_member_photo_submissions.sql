ALTER TABLE foto_boeken ADD COLUMN agenda_id INTEGER REFERENCES agenda(id) ON DELETE SET NULL;
CREATE INDEX foto_boeken_agenda_id_idx ON foto_boeken(agenda_id);

CREATE TABLE foto_submissions (
    id SERIAL PRIMARY KEY,
    boek INTEGER NOT NULL REFERENCES foto_boeken(id) ON DELETE CASCADE,
    uploaded_by INTEGER NOT NULL REFERENCES leden(id) ON DELETE CASCADE,
    filepath TEXT NOT NULL,
    beschrijving TEXT NOT NULL DEFAULT '',
    submitted_on TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    reviewed_by INTEGER REFERENCES leden(id) ON DELETE SET NULL,
    reviewed_on TIMESTAMP WITH TIME ZONE
);

CREATE INDEX foto_submissions_boek_status_idx ON foto_submissions(boek, status);
