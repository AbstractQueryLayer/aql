-- The tables of ifcastle/jobs, MariaDB 10.6 or later (FOR UPDATE SKIP LOCKED, CHECK, expression defaults).
-- An application may add columns (a creator, say) and keys; the queue needs these columns, keys and checks.

-- One row per job; workers lease rows with SELECT … FOR UPDATE SKIP LOCKED.
CREATE TABLE jobs (
  id          BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  kind        VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,   -- the JobKinds registry owns the list
  payload     LONGTEXT          NOT NULL,     -- JSON arguments, shape per kind
  subjectKind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,       -- for lookups and cancellation
  subjectId   BIGINT UNSIGNED   NULL,
  state       VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'queued',
  priority    SMALLINT          NOT NULL DEFAULT 0,          -- lower runs first
  runAfter    DATETIME(6)       NOT NULL DEFAULT (UTC_TIMESTAMP(6)),
  attempts    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  maxAttempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  lockedBy    VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,       -- worker id holding the lease
  lockedUntil DATETIME(6)       NULL,        -- lease end; an expired lease returns the job to the queue
  leaseToken  BINARY(16)        NULL,        -- random per lease; renew, complete and fail name it
  dedupKey    VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NULL,      -- set only while queued or running
  lastError   VARCHAR(2000)     NULL,
  createdAt   DATETIME(6)       NOT NULL DEFAULT (UTC_TIMESTAMP(6)),
  startedAt   DATETIME(6)       NULL,
  finishedAt  DATETIME(6)       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jobs_dedup (dedupKey),
  KEY idx_jobs_queue (state, priority, runAfter, id),
  KEY idx_jobs_lease (state, lockedUntil),
  KEY idx_jobs_subject (subjectKind, subjectId),
  CONSTRAINT chk_jobs_state CHECK (state IN ('queued', 'running', 'succeeded', 'failed', 'cancelled')),
  CONSTRAINT chk_jobs_lease CHECK ((state = 'running') = (lockedBy IS NOT NULL AND lockedUntil IS NOT NULL)),
  CONSTRAINT chk_jobs_lease_token CHECK ((state = 'running') = (leaseToken IS NOT NULL)),
  CONSTRAINT chk_jobs_dedup CHECK (dedupKey IS NULL OR state IN ('queued', 'running')),
  CONSTRAINT chk_jobs_finished CHECK ((finishedAt IS NOT NULL) = (state IN ('succeeded', 'failed', 'cancelled'))),
  CONSTRAINT chk_jobs_subject CHECK ((subjectKind IS NULL) = (subjectId IS NULL)),
  CONSTRAINT chk_jobs_attempts CHECK (maxAttempts > 0 AND attempts <= maxAttempts),
  CONSTRAINT chk_jobs_payload CHECK (JSON_VALID(payload))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per finished attempt, written once.
CREATE TABLE job_attempts (
  jobId      BIGINT UNSIGNED   NOT NULL,
  attempt    SMALLINT UNSIGNED NOT NULL,
  worker     VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  startedAt  DATETIME(6)       NOT NULL,
  finishedAt DATETIME(6)       NOT NULL,
  outcome    VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  error      VARCHAR(2000)     NULL,
  PRIMARY KEY (jobId, attempt),
  CONSTRAINT fk_job_attempts_job FOREIGN KEY (jobId) REFERENCES jobs (id) ON DELETE CASCADE,
  CONSTRAINT chk_job_attempts_outcome CHECK (outcome IN ('succeeded', 'failed', 'lease_expired', 'cancelled')),
  CONSTRAINT chk_job_attempts_number CHECK (attempt > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
