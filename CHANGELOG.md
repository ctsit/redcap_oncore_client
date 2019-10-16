# Change Log
All notable changes to the OnCore Client module will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).


## [2.3.1] - 2019-10-16
### Changed
- Fix typos in README and Changelog (Kyle Chesney, Philip Chase)
- Switch README to 'example.edu' host names (Philip Chase)
- Add link to the ocr api git repo (Philip Chase)


## [2.3.0] - 2019-08-27
### Added
- Add 'REDCap Record ID' section to README.md (Philip Chase)
- enable access to SequenceNumber (Kyle Chesney)

### Changed
- trim values on fetch so diff won't flag whitespace (Kyle Chesney)
- preemptively reject data that would cause null table primary keys (Kyle Chesney)


## [2.2.1] - 2019-07-29
### Changed
- Make isListUpdated check only the current project to avoid collisions with other users (Kyle Chesney)


## [2.2.0] - 2019-07-18
### Added
- Enable use of redcap user_inst_id or RCE db for institution id (Kyle Chesney)

### Changed
- Increase redcap-version-min to 9.0.1 (Philip Chase)


## [2.1.0] - 2019-07-09
### Added
- Add 'Institutional IDs' section, etc. to README (Philip Chase)
- Implement option to use a server variable to set users' staff_id (Kyle Chesney)

### Changed
- Replace entity SQL query strings with EntityQuery abstraction (Kyle Chesney)


## [2.0.0] - 2019-06-26
### Added
- Add support for UF OCR API (Kyle Chesney)
- Optionally read complete list of protocols via UF OCR API (Kyle Chesney)
- Verify logged in user is on study staff before showing enrollment data (Kyle Chesney)
- Notify users which records were not pulled and which variable collided (Kyle Chesney)

### Changed
- Implement better entity table names, update README with changes (Kyle Chesney)
- Update README to reflect non-longitudinal projects are supported (Kyle Chesney)
- Update to support REDCap v9 (Kyle Chesney)
- Overwrite instead of adding new records (Kyle Chesney)
- add digNestedData function to add previously missed data (Kyle Chesney)
- Add often missed statuses, provide unused method to check for more (Kyle Chesney)
- solve subjects_diff db replicating subjects across in each project (Kyle Chesney)
- Do not check on subjects in different projects, address issue #17 (Kyle Chesney)
- correct issue with only null dates being entered, implement checking (Kyle Chesney)


## [1.2.0] - 2019-01-23
### Added
- Write 'Synch OnCore subjects' section of README (Philip Chase)

### Changed
- Update code and docs for redcap_entity 2.1.0 (Philip Chase, Tiago Bember Simeao)
- Fixing diff refresh criteria. (Tiago Bember Simeao)


## [1.1.0] - 2018-10-03
### Added
- Adding Pull OnCore Subjects page. (Tiago Bember Simeao)

### Changed
- Turning logs into an entity. (Tiago Bember Simeao)
- Adapting code to the new REDCap Entity features. (Tiago Bember Simeao)


## [1.0.0] - 2018-08-30
### Summary
- Initial release of a REDCap external module that provides integration with OnCore.
- It allows REDCap project builders to associate a REDCap project with an OnCore protocol.
- It allows developers to call the OnCore API via SOAP.
