-- Apply once to an existing database created before the lending rules were added.
ALTER TABLE Loans
  ADD COLUMN DueDate DATE NULL AFTER LoanDate,
  ADD COLUMN RenewalCount TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER ReturnDate,
  ADD COLUMN FineAmount INT UNSIGNED NOT NULL DEFAULT 0 AFTER RenewalCount,
  ADD INDEX idx_loans_member_active (MemberID, ReturnDate),
  ADD INDEX idx_loans_due_active (ReturnDate, DueDate);

UPDATE Loans
SET DueDate = DATE_ADD(LoanDate, INTERVAL 14 DAY)
WHERE DueDate IS NULL;

ALTER TABLE Loans
  MODIFY COLUMN DueDate DATE NOT NULL;

CREATE TABLE IF NOT EXISTS Reservations (
  ReservationID INT AUTO_INCREMENT PRIMARY KEY,
  MemberID      INT NOT NULL,
  ISBN          VARCHAR(20) NOT NULL,
  ReservedAt    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Status        ENUM('waiting', 'ready', 'completed', 'cancelled') NOT NULL DEFAULT 'waiting',
  ReadyAt       DATETIME NULL,
  CompletedAt   DATETIME NULL,
  FOREIGN KEY (MemberID) REFERENCES Members(MemberID),
  FOREIGN KEY (ISBN)     REFERENCES Books(ISBN),
  INDEX idx_reservations_queue (ISBN, Status, ReservedAt, ReservationID),
  INDEX idx_reservations_member (MemberID, Status)
) ENGINE=InnoDB;
