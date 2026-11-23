moodle-certificateelement_enrolstart
====================================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-certificateelement_enrolstart/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-certificateelement_enrolstart/actions/workflows/ci.yml?query=branch%3Amain)

A certificate element for the Workplace certificate tool (`tool_certificate`) that prints the date
the learner's enrolment in the course started.

The certificate tool ships a date element with the date of issue and the expiry date only. This
element adds the start of the enrolment, so a course certificate can say "from 10 March 2026 to
20 May 2026". It works with every enrolment method, including the ones that record no start date,
and it fixes the date when the certificate is issued: an enrolment edited, suspended or removed
afterwards changes no certificate.


Which date is printed
---------------------

A learner can hold one enrolment per enrolment method of a course. For each of them:

- the start date the method recorded is used when there is one. Self enrolment, the manual
  enrolment screen, enrolment on payment and an approved application (`enrol_apply`) record it;
- otherwise the date the enrolment was created is used. Cohort sync, an external database, LDAP,
  category enrolments and the enrolment of whoever creates a course record no start date.

The element prints the earliest of those dates among the enrolments in force when the certificate
is issued: active, on an enabled method, not past their end date. When none is in force (the method
was disabled, or the period ran out before the certificate was issued) it prints the earliest among
the active ones. A suspended enrolment never counts, which also keeps a pending application out:
its row is created when the person applies, not when they are let in.

Two things to know:

- **A restored or copied course.** A restore keeps the start date of each enrolment and creates the
  enrolment rows again. An enrolment that had no start date therefore reads as started on the day of
  the restore.
- **A certificate issued outside a course** (from the certificate tool itself, or by a programme)
  has no course to read and prints nothing.


Requirements
------------

- Moodle 5.3 or later (tested up to Moodle 5.3)
- `tool_certificate` 2026100601 or later. The course certificate activity (`mod_coursecertificate`)
  is what issues certificates for a course; this plugin needs no change in it.


Installation
------------

Install the plugin like any other plugin to folder `/admin/tool/certificate/element/enrolstart`.

See http://docs.moodle.org/en/Installing_plugins for details on installing
Moodle plugins.

It works without configuration. From the next certificate issued for a course, the start date is
stored with the issue.


Usage
-----

In Site administration > Certificates > Manage certificate templates, edit a template and add the
"Enrolment start date" element. Choose the date format; font, colour, position and width are the
certificate tool's usual fields. The template preview shows today's date.

The date is stored with every certificate issued for a course, whether or not its template shows
it, so a template that gains the element later prints the right date when its certificates are
regenerated.

Certificates issued before the plugin was installed carry no stored date. For them the element
reads the learner's enrolments as they stand when the PDF is generated; issue the certificate again
to fix the date.


Data stored
-----------

The plugin has no table, no setting, no capability and no user preference.

It adds one value to a row the certificate tool owns: the key `certificateelement_enrolstart` in
the JSON of `tool_certificate_issues.data`, holding the enrolment start date as a Unix time (0 when
the learner had no active enrolment). It is kept as long as the certificate issue and is removed
with it: a deletion request for the learner deletes the issue through the privacy provider of
`tool_certificate`. A data request does **not** export it: that provider exports the issue's code,
dates and custom fields, not the `data` column, so the date reaches the learner only as printed on
the certificate file.


Troubleshooting
---------------

- **The element prints nothing.** The certificate was issued outside a course, or the learner had
  no active enrolment in the course when it was issued.
- **The date is the day the course was restored.** The original enrolment had no start date; see
  "Which date is printed".
- **The date is the day somebody was added to a cohort, not the day they first opened the course.**
  Cohort sync records no start date, so the date the enrolment was created is used.


Translations
------------

The English strings ship in the package. The Brazilian Portuguese translation
is maintained in this repository (`lang/pt_br`) and reaches sites through
[AMOS](https://lang.moodle.org/), as the plugins directory requires; it is left
out of the release package.


License
-------

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

Copyright: 2026 Anderson Blaine
