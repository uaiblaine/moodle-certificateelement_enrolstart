@tool @tool_certificate @certificateelement @certificateelement_enrolstart @javascript
Feature: Adding the enrolment start date element to a certificate template
  In order to print when a learner's enrolment in the course started
  As a certificate manager
  I need to add the element to a template and choose its date format

  Background:
    Given the following certificate templates exist:
      | name          | numberofpages |
      | Certificate 1 | 1             |

  Scenario: Add the element and change its date format
    Given I log in as "admin"
    And I change window size to "large"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I follow "Certificate 1"
    When I add the element "Enrolment start date" to page "1" of the "Certificate 1" site certificate template
    Then I should see "Add 'Enrolment start date' element" in the ".modal.show .modal-header" "css_element"
    And I should see "The date is fixed when the certificate is issued" in the ".modal.show .modal-body" "css_element"
    And I set the following fields to these values:
      | Element name | Start             |
      | Date format  | strftimemonthyear |
    And I click on "Save" "button" in the ".modal.show .modal-footer" "css_element"
    And I should see "Start" in the "[data-region='elementlist']" "css_element"
    And I click on "Edit 'Start'" "link" in the "[data-region='elementlist']" "css_element"
    And the following fields match these values:
      | Element name | Start             |
      | Date format  | strftimemonthyear |
