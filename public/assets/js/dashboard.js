/* Counts and source tables remain accessible even if Chart.js cannot load.
 * Presentation-only chart refinement; no labels, values or data calls changed.
 */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var chartTypeNodes=document.querySelectorAll('.dashboard-page canvas[data-chart-type]');
        chartTypeNodes.forEach(function (canvas) {
            if (!window.Chart) {
                var missingChart=canvas.closest('.chart-box');
                if (missingChart) missingChart.hidden=true;
                return;
            }
            try {
                var labels=JSON.parse(canvas.dataset.chartLabels);
                var values=JSON.parse(canvas.dataset.chartCounts).map(Number);
                var total=values.reduce(function(sum,value){return sum+value;},0);
                var pie=canvas.dataset.chartType==='doughnut';
                var reducedMotion=window.matchMedia &&
                    window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                var fonts={family:"'Poppins', sans-serif",size:11};
                var dataset={
                    label:'Records',
                    data:values,
                    backgroundColor:pie?['#304866','#ce344c']:'#be2c43',
                    hoverBackgroundColor:pie?['#253a55','#ad2037']:'#a82237',
                    borderWidth:pie?4:0,
                    borderColor:'#ffffff',
                    borderRadius:pie?0:7,
                    maxBarThickness:38,
                    categoryPercentage:.66,
                    barPercentage:.76
                };
                new window.Chart(canvas,{
                    type:canvas.dataset.chartType,
                    data:{labels:labels,datasets:[dataset]},
                    options:{
                        responsive:true,
                        maintainAspectRatio:false,
                        animation:reducedMotion?false:{duration:320},
                        ...(pie?{cutout:'73%',layout:{padding:{top:5,bottom:8}}}
                            :{layout:{padding:{top:10,right:9,bottom:1,left:4}}}),
                        plugins:{
                            legend:{
                                display:pie,
                                position:'bottom',
                                align:'center',
                                labels:{
                                    usePointStyle:true,
                                    pointStyle:'circle',
                                    boxWidth:8,
                                    boxHeight:8,
                                    padding:20,
                                    color:'#596a80',
                                    font:fonts
                                }
                            },
                            tooltip:{
                                backgroundColor:'#1c2c43',
                                titleColor:'#ffffff',
                                bodyColor:'#f3f6fb',
                                padding:12,
                                cornerRadius:10,
                                displayColors:pie,
                                titleFont:{family:fonts.family,size:11,weight:'700'},
                                bodyFont:{family:fonts.family,size:11},
                                callbacks:{
                                    label:function(item){
                                        var count=values[item.dataIndex];
                                        return labels[item.dataIndex]+': '+count+' ('+
                                            (total?(100*count/total).toFixed(1):'0')+'%)';
                                    }
                                }
                            }
                        },
                        ...(pie?{}:{
                            scales:{
                                x:{
                                    grid:{display:false},
                                    border:{display:false},
                                    ticks:{
                                        color:'#7e8ca0',
                                        font:{family:fonts.family,size:10},
                                        maxRotation:0,
                                        autoSkip:true,
                                        padding:9
                                    }
                                },
                                y:{
                                    beginAtZero:true,
                                    border:{display:false,dash:[3,3]},
                                    grid:{color:'#edf1f5'},
                                    ticks:{
                                        precision:0,
                                        padding:9,
                                        color:'#8c99aa',
                                        font:{family:fonts.family,size:10}
                                    }
                                }
                            }
                        })
                    }
                });
            } catch(error) {
                var failedChart=canvas.closest('.chart-box');
                if (failedChart) failedChart.hidden=true;
            }
        });
    });
}());
