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
