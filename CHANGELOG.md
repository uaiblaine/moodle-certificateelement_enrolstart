# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Added

- The "Enrolment start date" certificate element: the earliest start among the learner's enrolments
  in the course, the date the enrolment was created standing in when the method records no start
  date. The date is stored with the certificate issue when it is created (version 2026100500).

### Changed

- A real privacy provider replaces the null provider (version 2026100501): a data request exports
  the enrolment start date stored with the certificate issue, and a deletion request removes it
  from the issue's data, leaving the rest of the data in place.
