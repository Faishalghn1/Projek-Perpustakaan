-- The MariaDB image creates DB_NAME before running this script.
-- Keeping the schema scoped to the configured database avoids hard-coded database names.
CREATE TABLE IF NOT EXISTS Books (
  ISBN     VARCHAR(20)  PRIMARY KEY,
  Title    VARCHAR(255) NOT NULL,
  Author   VARCHAR(255) NOT NULL,
  Genre    VARCHAR(100),
  Quantity INT NOT NULL DEFAULT 0 CHECK (Quantity >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Members (
  MemberID INT AUTO_INCREMENT PRIMARY KEY,
  Name     VARCHAR(255) NOT NULL,
  Email    VARCHAR(255) UNIQUE,
  Phone    VARCHAR(20)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Loans (
  LoanID       INT AUTO_INCREMENT PRIMARY KEY,
  MemberID     INT NOT NULL,
  ISBN         VARCHAR(20) NOT NULL,
  LoanDate     DATE NOT NULL,
  DueDate      DATE NOT NULL,
  ReturnDate   DATE NULL,
  RenewalCount TINYINT UNSIGNED NOT NULL DEFAULT 0,
  FineAmount   INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (MemberID) REFERENCES Members(MemberID),
  FOREIGN KEY (ISBN)     REFERENCES Books(ISBN),
  INDEX idx_loans_member_active (MemberID, ReturnDate),
  INDEX idx_loans_due_active (ReturnDate, DueDate)
) ENGINE=InnoDB;

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

INSERT IGNORE INTO Books (ISBN, Title, Author, Genre, Quantity) VALUES
  ('978-0140449136', 'The Odyssey', 'Homer', 'Classics', 5),
  ('978-0439139595', 'Harry Potter and the Goblet of Fire', 'J.K. Rowling', 'Fantasy', 10),
  ('978-0262033848', 'Introduction to Algorithms', 'Cormen et al.', 'Computer Science', 3);

INSERT IGNORE INTO Members (Name, Email, Phone) VALUES
  ('John Doe',   'john.doe@example.com',   '08012345678'),
  ('Mary James', 'mary.james@example.com', '08087654321');
