#!/usr/bin/env bash
cd `dirname $0`
for version in "1.2.0" "1.1.1"
do
	versiondashes=$(echo -n "$version" | sed -e "s/\./\-/g")
	if [ -d "dompdf-$version" ]; then
		echo "directory exists dompdf-$version"
	else
		echo "directory does not exist dompdf-$version"
		echo "downloading version $version..."
		wget "https://github.com/dompdf/dompdf/releases/download/v$version/dompdf_$versiondashes.zip"
		unzip "dompdf_$versiondashes.zip"
		rm "dompdf_$versiondashes.zip"
		mv "dompdf" "dompdf-$version"
		echo "donwloaded version dompdf_$version.zip to dompdf-$version/"
	fi
done
