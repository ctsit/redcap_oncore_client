library(tidyverse)

# How many records are needed?
records <- 100

# generate a vector of values for each column with a uniform distribution
record_id <- seq(from = 1, to = records)
onstudydate <- Sys.Date() - sample(rep(1:20, 10), records)
gender <- sample(rep(c("M", "F", "B", NA), 50), records)
race <- sample(rep(c("01", "03", "04", "05", "06", "07", "99", NA), 50), records)
ethnicity <- sample(rep(c("1", "2", "3", "9", NA), 50), records)

# Uncomment these lines if you need to exclude coded variables
# race <- rep(NA, records)
# ethnicity <- race

# Combine my columns and write a test dataset
test_data <- tibble(record_id, onstudydate, gender, race, ethnicity)
write_csv(test_data, "summary_accrual_test_data.csv", na = "")
