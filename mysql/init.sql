- Basis data sudah dibuat oleh variabel MARIADB_DATABASE;
-- baris berikut memastikan skrip tetap aman dijalankan ulang.
CREATE DATABASE IF NOT EXISTS LibraryDB
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE LibraryDB;

-- Tabel Buku
CREATE TABLE IF NOT EXISTS Books (
  ISBN     VARCHAR(20)  PRIMARY KEY,
  Title    VARCHAR(255) NOT NULL,
  Author   VARCHAR(255) NOT NULL,
  Genre    VARCHAR(100),
  Quantity INT NOT NULL DEFAULT 0 CHECK (Quantity >= 0)
) ENGINE=InnoDB;

-- Tabel Anggota
CREATE TABLE IF NOT EXISTS Members (
  MemberID INT AUTO_INCREMENT PRIMARY KEY,
  Name     VARCHAR(255) NOT NULL,
  Email    VARCHAR(255) UNIQUE,
  Phone    VARCHAR(20)
) ENGINE=InnoDB;

-- Tabel Peminjaman
CREATE TABLE IF NOT EXISTS Loans (
  LoanID     INT AUTO_INCREMENT PRIMARY KEY,
  MemberID   INT NOT NULL,
  ISBN       VARCHAR(20) NOT NULL,
  LoanDate   DATE NOT NULL,
  ReturnDate DATE NULL,
  FOREIGN KEY (MemberID) REFERENCES Members(MemberID),
  FOREIGN KEY (ISBN)     REFERENCES Books(ISBN)
) ENGINE=InnoDB;

-- Data contoh
INSERT INTO Books (ISBN, Title, Author, Genre, Quantity) VALUES
  ('978-0140449136', 'The Odyssey', 'Homer', 'Classics', 5),
  ('978-0439139595', 'Harry Potter and the Goblet of Fire', 'J.K. Rowling', 'Fantasy', 10),
  ('978-0262033848', 'Introduction to Algorithms', 'Cormen et al.', 'Computer Science', 3);

INSERT INTO Members (Name, Email, Phone) VALUES
  ('John Doe',   'john.doe@example.com',   '08012345678'),
  ('Mary James', 'mary.james@example.com', '08087654321');

INSERT INTO Loans (MemberID, ISBN, LoanDate, ReturnDate) VALUES
  (1, '978-0140449136', '2025-11-01', '2025-11-15'),
  (1, '978-0439139595', '2025-11-05', NULL),
  (2, '978-0262033848', '2025-11-10', NULL);