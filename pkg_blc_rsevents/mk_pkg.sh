cur=`pwd`
relbase="25.52"

rel="${1:-`php maxrev.php`}"

re='^[0-9]+$'
date=`date +%Y-%m-%d`
base="pkg_blc_rsevents";
rel="$relbase.$rel"
rm -rf /tmp/$rel

svn export file:///var/svn/xmlstuff/projecten/blc/trunk/pkg_blc_rsevents /tmp/$rel

cd /tmp/$rel/
cp /var/www/downloads.brokenlinkchecker.dev/plg_blc_rseventsevent.zip .
cp /var/www/downloads.brokenlinkchecker.dev/plg_blc_rseventslocation.zip .



for f in pkg_blc_rsevents.xml ; do
	sed --in-place -e "s#<version>.*</version>#<version>$rel</version>#"  -e "s#<creationDate>.*</creationDate>#<creationDate>$date</creationDate>#" $f
done

#for f in script.php ; do
	#echo "headers for: $f"
	#sed --in-place -e s"#@version.*#@version    $rel#" $f
#done

file="/var/www/downloads.brokenlinkchecker.dev/$base.zip"
rm -f $file
zip -r  -qq "$file" plg_blc_rseventsevent.zip plg_blc_rseventslocation.zip pkg_blc_rsevents.xml script.php language 
#rm -f com_blc/blc.xml

sha256=`sha256sum -b  $file|cut -d' ' -f1`;

cat >  /var/www/downloads.brokenlinkchecker.dev/$base.xml << EOM
<?xml version="1.0" ?>
<updates>
        <update>
		<name> RsEvents Pro Plugins</name>
	        <description>Broken Link Checker RsEvents Pro Plugins - Jooma 5.2</description>
                <element>pkg_blc_rsevents</element>
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

		<targetplatform name="joomla" version="5.(2|3)"/>
                <php_minimum>8.2</php_minimum>
 		<supported_databases mysql="8.0.13" mariadb="10.2.1"/>
        </update>
</updates>
EOM
echo https://downloads.brokenlinkchecker.dev/$base.zip
ls -l $file
cd $cur

