<?php
declare(strict_types=1);
function serveSitemap():never {
 header('Content-Type: application/xml; charset=utf-8');
 if(getenv('CEPS_ENV')!=='production')header('X-Robots-Tag: noindex, nofollow');
 $origin=siteOrigin();
 $xml='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
 $entry=static function(string $url,array $alternates=[],?string $modified=null)use(&$xml,$origin):void{
  $xml.='<url><loc>'.esc($origin.$url).'</loc>';
  if($modified){try{$xml.='<lastmod>'.(new DateTimeImmutable($modified))->format('Y-m-d').'</lastmod>';}catch(Exception){}}
  foreach($alternates as $lang=>$path)$xml.='<xhtml:link rel="alternate" hreflang="'.$lang.'" href="'.esc($origin.$path).'"/>';
  $xml.='</url>';
 };
 $bsRoutes=seoRoutes(false);$enRoutes=seoRoutes(true);$deRoutes=seoRoutes('de');
 foreach($bsRoutes as $old=>$bs){
  if($old==='/Pretraga')continue;
  $en=languagePrefix($enRoutes[$old],true);$alternates=['bs'=>$bs,'en'=>$en,'x-default'=>$bs];
  if(germanPageAvailable($old))$alternates['de']=languagePrefix($deRoutes[$old],'de');
  $entry($bs,$alternates);$entry($en,$alternates);
  if(isset($alternates['de']))$entry($alternates['de'],$alternates);
 }
 $bsNews=seoNewsRoutes(false);$enNews=seoNewsRoutes(true);$deNews=seoNewsRoutes('de');
 foreach(database()->query('SELECT ID,Hr,En,De,Datum FROM Novosti WHERE Hr=1 OR En=1 OR De=1') as $row){
  $id=(string)$row['ID'];$alternates=[];
  if($row['Hr'])$alternates['bs']=$bsNews[$id];
  if($row['En'])$alternates['en']=languagePrefix($enNews[$id],true);
  if($row['De'])$alternates['de']=languagePrefix($deNews[$id],'de');
  if($row['Hr'])$entry($alternates['bs'],$alternates,$row['Datum']);
  if($row['En'])$entry($alternates['en'],$alternates,$row['Datum']);
  if($row['De'])$entry($alternates['de'],$alternates,$row['Datum']);
 }
 echo $xml.'</urlset>';exit;
}
