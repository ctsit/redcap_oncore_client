# Generate datasets for the Summary Accrual feature testing

library(tidyverse)
library(lubridate)

# control limits on the test data
records <- 100
max_age <- 120

# generate a vector of values for each column with a uniform distribution
record_id <- seq(from = 1, to = records)
onstudydate <- Sys.Date() - sample(1:20, records, replace = T)
# erase one onstudydate at random
# No let's not do that until Harshita fixes this issue: https://github.com/UF-OCR/ocr-api/issues/18
# onstudydate[sample(1:records, 1)] <- as.Date(NA)
gender <- sample(c("M", "F", "B", NA), records, replace = T)
race <- sample(c("01", "03", "04", "05", "06", "07", "99", NA), records, replace = T)
ethnicity <- sample(c("1", "2", "3", "9", NA), records, replace = T)

race___01 <- sample(c(1,0,0,0,0), records, replace = T)
race___03 <- sample(c(1,0,0,0,0), records, replace = T)
race___04 <- sample(c(1,0,0,0,0), records, replace = T)
race___05 <- sample(c(1,0,0,0,0), records, replace = T)
race___06 <- sample(c(1,0,0,0,0), records, replace = T)
race___07 <- sample(c(1,0,0,0,0), records, replace = T)

# Create matching ages and DOBs with randomly erased DOB and age
trues_per_false <- 15
preserve_age <- sample(c(rep(T,trues_per_false),F), records, replace = T)
preserve_dob <- sample(c(rep(T,trues_per_false),F), records, replace = T)
preserve_both <- sample(c(rep(T,trues_per_false),F), records, replace = T)
age_at_enrollment_in_days <- sample(1:(365*max_age), records)
age_at_enrollment_in_years <- case_when(preserve_age & preserve_both ~ round(age_at_enrollment_in_days/365.25), TRUE ~ as.numeric(NA))
date_of_birth <- case_when(preserve_dob & preserve_both ~ (onstudydate - age_at_enrollment_in_days), TRUE ~ as.Date(NA))

# Combine my columns and write a test dataset
test_data <- tibble(record_id, onstudydate, gender, race___01, race___03, race___04, race___05, race___06, race___07, ethnicity, dob = date_of_birth, age_at_enrollment = age_at_enrollment_in_years)
write_csv(test_data, "summary_accrual_test_data.csv", na = "")

# create dataset that excludes coded variables
test_data %>% select(-ethnicity, -contains("race")) %>% write_csv(., "summary_accrual_test_data_imagine.csv", na = "")
