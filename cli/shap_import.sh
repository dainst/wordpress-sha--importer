#!/bin/bash



echo "[SHAP command line import script]"

if [ -z "$1" ] || [ -z "$2" ]
then
    echo "usage: sh shap_import <start_page> <end_page>"
    exit
fi

echo "import from page $1 to $2"

SHAP_IMPORT_UUID=$(cat /dev/urandom | tr -dc "a-zA-Z0-9_" | fold -w 32 | head -n 1)
echo $SHAP_IMPORT_UUID

touch "import_${SHAP_IMPORT_UUID}.log"

x=$1
while [ "$x" -le "$2" ]
do
    echo "import page $x"
    x=$(( $x + 1 ))

    resp=$(curl localhost:81/wp-json/shap_importer/v1/import/shap_easydb/1)
    echo  resp >> "import_${SHAP_IMPORT_UUID}.log"
    # STAND TODO get log out out json

done

