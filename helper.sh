:
#run nightly by cron account jim
# run helpers for all servers linked to this instance of /dig
for f in ${1-base-*}; do 
   curl http://divvydao.org/diglife/webhooks/helper_bots.php?file=$f
   for helper in helper_names.php helper_hooks.php helper_avatars.php; do 
      curl http://divvydao.org/diglife/webhooks/$helper?file=$f; 
   done; 
done
