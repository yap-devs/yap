-- monthly traffic report
select date_format(user_stats.created_at, '%Y-%m')                                                 as month,
       user_id,
       users.name,
       round(sum(user_stats.traffic_downlink + user_stats.traffic_uplink) / 1024 / 1024 / 1024, 2) as monthly_traffic_gb
from user_stats
         inner join users on user_stats.user_id = users.id
group by user_id, month
order by month desc, monthly_traffic_gb desc;

-- monthly income report
select date_format(payments.created_at, '%Y-%m') as month,
       sum(payments.amount)                      as monthly_income
from payments
where payments.status = 'paid'
group by month
order by month desc;

-- monthly cost report
select date_format(balance_details.created_at, '%Y-%m') as month,
       sum(balance_details.amount)                      as monthly_cost
from balance_details
where balance_details.amount < 0
  and user_id > 5
group by month
order by month desc;

-- yesterday and today traffic report
select date_format(user_stats.created_at, '%Y-%m-%d')                                              as day,
       user_id,
       users.name,
       round(sum(user_stats.traffic_downlink + user_stats.traffic_uplink) / 1024 / 1024 / 1024, 2) as daily_traffic_gb
from user_stats
         inner join users on user_stats.user_id = users.id
where date_format(user_stats.created_at, '%Y-%m-%d') = date_format(now(), '%Y-%m-%d')
   or date_format(user_stats.created_at, '%Y-%m-%d') = date_format(date_sub(now(), interval 1 day), '%Y-%m-%d')
group by user_id, day
order by day, daily_traffic_gb desc;

-- user active package report
select user_packages.id,
       users.name,
       packages.name,
       user_packages.ended_at,
       round(user_packages.remaining_traffic / 1024 / 1024 / 1024, 2) as remaining_gb,
       round(packages.traffic_limit / 1024 / 1024 / 1024, 2)          as total_gb
from users
         inner join user_packages on users.id = user_packages.user_id
         inner join packages on user_packages.package_id = packages.id
where user_packages.status = 'active'
  and users.id > 5;
