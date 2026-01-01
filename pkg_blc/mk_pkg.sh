cur=`pwd`
relbase="25.44"

rel="${1:-`php maxrev.php`}"

re='^[0-9]+$'
date=`date +%Y-%m-%d`
base="pkg_blc";
rel="$relbase.$rel"
rm -rf /tmp/$rel

git commit -am"Sync $rel";
git tag -s $rel -m"Tag for package $rel";
git push origin --tags

svn export file:///var/svn/xmlstuff/projecten/blc/trunk/pkg_blc /tmp/$rel

cd /tmp/$rel/
cp /var/www/downloads.brokenlinkchecker.dev/plg_blc_content.zip .
cp /var/www/downloads.brokenlinkchecker.dev/plg_system_blc.zip .
cp /var/www/downloads.brokenlinkchecker.dev/com_blc.zip .
cp /var/www/downloads.brokenlinkchecker.dev/mod_blc_admin.zip .


for f in pkg_blc.xml ; do
	sed --in-place -e "s#<version>.*</version>#<version>$rel</version>#"  -e "s#<creationDate>.*</creationDate>#<creationDate>$date</creationDate>#" $f
done

#for f in script.php ; do
	#echo "headers for: $f"
	#sed --in-place -e s"#@version.*#@version    $rel#" $f
#done

file="/var/www/downloads.brokenlinkchecker.dev/$base.zip"
rm -f $file
zip -r  -qq "$file" com_blc.zip plg_blc_content.zip  mod_blc_admin.zip plg_system_blc.zip pkg_blc.xml script.php language
#rm -f com_blc/blc.xml

sha256=`sha256sum -b  $file|cut -d' ' -f1`;

cat >  /var/www/downloads.brokenlinkchecker.dev/$base.xml << EOM
<?xml version="1.0" ?>
<updates>
        <update>
		<name>BLC</name>
	      <description>Broken Link Checker Package Jooma 4.4 - 5.0 - 6.0</description>
                <element>pkg_blc</element>
                <type>package</type>
                <client>site</client>
                <version>$rel</version>
                <infourl title="brambring.nl - Broken Link Checker">https://brokenlinkchecker.dev/</infourl>
                <downloads>
                        <downloadurl type="full" format="zip">https://downloads.brokenlinkchecker.dev/$base.zip</downloadurl>
                </downloads>
                <sha256>$sha256</sha256>
                <tags>
                        <tag>stable</tag>
                </tags>
                <maintainer>Bram Brambring</maintainer>
                <maintainerurl>https://brambring.nl</maintainerurl>
                <section>-</section>
                <changelogurl>https://downloads.brokenlinkchecker.dev/changelog-pkg_blc.xml</changelogurl>
		<targetplatform name="joomla" version="(5.(0|1|2|3|4)|4.(4))|6\.(0|1)"/>
                <php_minimum>8.2</php_minimum>
 		<supported_databases mysql="8.0.13" mariadb="10.2.1"/>
        </update>
</updates>
EOM
echo https://downloads.brokenlinkchecker.dev/$base.zip
ls -l $file
cd $cur
php collection.php
php change2xml.php -e pkg_blc
